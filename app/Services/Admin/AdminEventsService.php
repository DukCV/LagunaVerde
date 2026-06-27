<?php

namespace App\Services\Admin;

use App\DTOs\Admin\AdminEventItemDto;
use App\Models\Event;
use App\Repositories\Admin\AdminEventsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio de gestión de eventos para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas al AdminEventsRepository.
 *  - Transformar colecciones de modelos Eloquent en DTOs listos para la vista.
 *  - Construir los arrays de opciones para los selectores de filtro.
 *  - Ser la única dependencia que el componente Livewire necesita inyectar.
 *
 * El componente Livewire no conoce ni el modelo Event ni el repositorio;
 * solo trabaja con DTOs y arrays de opciones primitivos.
 */
class AdminEventsService
{
    // Opciones del selector de orden — lista estática, no depende de la BD.
    // Las claves coinciden exactamente con AdminEventsRepository::SORT_MAP.
    private const SORT_OPTIONS = [
        'recientes'       => 'Publicados recientemente',
        'proximos'        => 'Más próximos a celebrarse',
        'lejanos'         => 'Más lejanos a celebrarse',
        'mas-inscritos'   => 'Con más inscritos',
        'menos-inscritos' => 'Con menos inscritos',
    ];

    public function __construct(
        private readonly AdminEventsRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por EventsManagement
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador de eventos con cada ítem transformado a AdminEventItemDto.
     *
     * Usa through() para transformación lazy sobre el paginador —
     * no carga toda la colección en memoria antes de transformar.
     *
     * @return LengthAwarePaginator<AdminEventItemDto>
     */
    public function getPaginatedEvents(
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
            fn ($event) => AdminEventItemDto::fromModel($event)
        );
    }

    /**
     * Conteo de eventos por estado para las métricas y tabs del encabezado.
     *
     * @return array<string, int>
     */
    public function getCountsByStatus(): array
    {
        return $this->repository->countsByStatus();
    }

    /**
     * Opciones del selector de categorías [valor => etiqueta].
     * Incluye la opción "Todas las categorías" como primera entrada.
     * Solo lista categorías de eventos con al menos un evento asignado —
     * así nunca se muestran categorías sin uso real en el filtro.
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
     * Opciones del selector de estado [valor => etiqueta].
     *
     * @return array<string, string>
     */
    public function getStatusOptions(): array
    {
        return [
            'todos'     => 'Todos los estados',
            'published' => 'Publicados',
            'draft'     => 'Borradores',
            'cancelled' => 'Cancelados',
            'closed'    => 'Finalizados',
        ];
    }

    /**
     * Opciones del selector de orden [valor => etiqueta].
     *
     * @return array<string, string>
     */
    public function getSortOptions(): array
    {
        return self::SORT_OPTIONS;
    }

    /**
     * Devuelve el estado actual de un evento directamente desde la BD.
     *
     * Usado por el componente para decidir si la cancelación es válida,
     * sin confiar en ningún valor enviado por el cliente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getEventStatus(int $id): string
    {
        return Event::select('id', 'status')->findOrFail($id)->status;
    }

    /**
     * Devuelve el nombre de un evento directamente desde la BD.
     *
     * Usado para construir el texto dinámico de los modales de confirmación
     * sin confiar en ningún valor enviado por el cliente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getEventTitle(int $id): string
    {
        return Event::select('id', 'name')->findOrFail($id)->name;
    }

    /**
     * Cancela un evento. Es una transición de un solo sentido: un evento
     * cancelado o finalizado no puede "reactivarse" desde este panel.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function cancelEvent(int $id): void
    {
        // findOrFail garantiza que el evento exista antes de intentar el UPDATE.
        Event::select('id')->findOrFail($id);

        $this->repository->cancel($id);
    }

    /**
     * Elimina permanentemente un evento junto con TODOS sus registros
     * relacionados (comentarios, media, inscripciones) y los archivos
     * físicos asociados.
     *
     * ARQUITECTURA DE TRANSACCIONES (mismo patrón que AdminNewsService):
     *  Las eliminaciones de registros de BD ocurren DENTRO de DB::transaction
     *  para garantizar atomicidad. Las rutas físicas se recopilan durante la
     *  transacción pero los archivos del disco solo se borran DESPUÉS de que
     *  la transacción confirme con éxito — evita huérfanos en Storage si la
     *  BD falla, y evita perder archivos recuperables si el borrado se revierte.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function deleteEvent(int $id): void
    {
        $archivosAEliminar = [];

        DB::transaction(function () use ($id, &$archivosAEliminar): void {
            Event::select('id')->findOrFail($id);

            $archivosAEliminar = $this->repository->deleteEventWithCascade($id);
        });

        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
