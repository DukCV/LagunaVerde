<?php

namespace App\Services\Admin;

use App\DTOs\Admin\AdminNewsItemDto;
use App\Models\News;
use App\Models\Scopes\PublishedScope;
use App\Repositories\Admin\AdminNewsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio de gestión de noticias para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas al AdminNewsRepository.
 *  - Transformar colecciones de modelos Eloquent en DTOs listos para la vista.
 *  - Construir los arrays de opciones para los selectores de filtro.
 *  - Ser la única dependencia que el componente Livewire necesita inyectar.
 *
 * El componente Livewire no conoce ni el modelo News ni el repositorio;
 * solo trabaja con DTOs y arrays de opciones primitivos.
 */
class AdminNewsService
{
    public function __construct(
        private readonly AdminNewsRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por NewsManagement
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador de noticias con cada ítem transformado a AdminNewsItemDto.
     *
     * Usa through() para transformación lazy sobre el paginador —
     * no carga toda la colección en memoria antes de transformar.
     *
     * @return LengthAwarePaginator<AdminNewsItemDto>
     */
    public function getPaginatedNews(
        string $search,
        string $status,
        string $category,
        string $sortBy,
        int    $perPage = 12
    ): LengthAwarePaginator {
        $paginator = $this->repository->paginate(
            $search, $status, $category, $sortBy, $perPage
        );

        return $paginator->through(
            fn ($news) => AdminNewsItemDto::fromModel($news)
        );
    }

    /**
     * Total de noticias en estado 'published' para la métrica del encabezado.
     */
    public function getPublishedCount(): int
    {
        return $this->repository->countPublished();
    }

    /**
     * Opciones del selector de categorías [valor => etiqueta].
     * Incluye la opción "Todas las categorías" como primera entrada.
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        $categories = $this->repository->availableCategories();

        return collect(['todas' => 'Todas las categorías'])
            ->merge($categories->mapWithKeys(fn ($name) => [$name => $name]))
            ->toArray();
    }

    /**
     * Alterna el estado de una noticia entre 'published' y 'disabled'.
     *
     * SEGURIDAD:
     *  - El nuevo estado se determina leyendo el estado ACTUAL desde la BD.
     *  - Nunca se confía en el estado enviado por el cliente.
     *  - findOrFail lanza ModelNotFoundException si el ID no existe.
     *
     * Lógica de transición:
     *  'disabled'     → 'published'
     *  cualquier otro → 'disabled'
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function toggleNewsStatus(int $id): void
    {
        // Opt-out del Global Scope: findOrFail debe encontrar noticias en CUALQUIER
        // estado (draft, archived, disabled) para poder alternar su estado.
        // Sin withoutGlobalScope(), el scope filtraría a publicadas únicamente
        // y lanzaría ModelNotFoundException en noticias no publicadas.
        $noticia = News::withoutGlobalScope(PublishedScope::class)
            ->select('id', 'status')
            ->findOrFail($id);

        $nuevoEstado = $noticia->status === 'disabled' ? 'published' : 'disabled';

        $this->repository->toggleStatus($id, $nuevoEstado);
    }

    /**
     * Devuelve el estado actual de una noticia directamente desde la BD.
     *
     * Usado por el componente para determinar qué texto mostrar en el modal
     * sin confiar en ningún valor enviado por el cliente.
     * Solo carga 'id' y 'status' para minimizar transferencia de datos.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getNewsStatus(int $id): string
    {
        // Opt-out del Global Scope: este método es llamado por el admin para conocer
        // el estado actual de CUALQUIER noticia, no solo de las publicadas.
        // Sin withoutGlobalScope(), el scope lanzaría ModelNotFoundException para
        // noticias en draft, archived o disabled, rompiendo el flujo del modal.
        return News::withoutGlobalScope(PublishedScope::class)
            ->select('id', 'status')
            ->findOrFail($id)
            ->status;
    }

    /**
     * Devuelve el título de una noticia directamente desde la BD.
     *
     * Usado para construir el texto dinámico del modal de confirmación de
     * eliminación sin confiar en ningún valor enviado por el cliente —
     * el mismo principio de seguridad que getNewsStatus().
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getNewsTitle(int $id): string
    {
        // Opt-out del Global Scope: el admin debe poder confirmar la eliminación
        // de noticias en CUALQUIER estado, no solo publicadas.
        return News::withoutGlobalScope(PublishedScope::class)
            ->select('id', 'title')
            ->findOrFail($id)
            ->title;
    }

    /**
     * Elimina permanentemente una noticia junto con TODOS sus registros
     * relacionados (comentarios, imágenes, videos, documentos) y los
     * archivos físicos asociados.
     *
     * ARQUITECTURA DE TRANSACCIONES (mismo patrón que NewsFormService):
     *  Las eliminaciones de registros de BD ocurren DENTRO de DB::transaction
     *  para garantizar atomicidad — si cualquier paso falla, todo se revierte
     *  y la noticia permanece intacta. Las rutas físicas se recopilan durante
     *  la transacción pero los archivos del disco solo se borran DESPUÉS de
     *  que la transacción confirme con éxito. Esto evita huérfanos en Storage
     *  si la BD falla, y evita perder archivos recuperables si el borrado de
     *  BD se revierte.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function eliminarNoticia(int $id): void
    {
        $archivosAEliminar = [];

        DB::transaction(function () use ($id, &$archivosAEliminar): void {
            // findOrFail garantiza existencia y lanza ModelNotFoundException si no
            // existe, abortando la transacción antes de tocar ningún registro.
            News::withoutGlobalScope(PublishedScope::class)
                ->select('id')
                ->findOrFail($id);

            $archivosAEliminar = $this->repository->deleteNewsWithCascade($id);
        });

        // Limpieza física TRAS confirmar la transacción — nunca antes, para no
        // perder archivos recuperables ante un rollback de BD.
        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Opciones del selector de estado [valor => etiqueta].
     * Los valores coinciden con News::STATUSES o 'todas' para sin filtro.
     *
     * @return array<string, string>
     */
    public function getStatusOptions(): array
    {
        return [
            'todas'     => 'Todos los estados',
            'published' => 'Publicadas',
            'scheduled' => 'Programadas',
            'draft'     => 'Borradores',
            'archived'  => 'Descontinuadas',
            'disabled'  => 'Deshabilitadas',
        ];
    }
}
