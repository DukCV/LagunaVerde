<?php

namespace App\Services\Admin;

use App\Models\News;
use App\Repositories\Admin\NewsFormRepository;
use App\Support\RichText\SanitizesRichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio de orquestación para el formulario de creación/edición de noticias.
 *
 * RESPONSABILIDADES:
 *  - Preparar los datos de carga inicial del formulario (modo edición).
 *  - Ejecutar el guardado completo dentro de una transacción DB atómica.
 *  - Sanitizar el contenido HTML del editor Trix para prevenir XSS.
 *  - Delegar la persistencia exclusivamente al NewsFormRepository.
 *
 * ARQUITECTURA DE TRANSACCIONES:
 *  Las operaciones de archivo (Storage) ocurren DENTRO de la transacción de BD
 *  para la escritura de nuevos archivos (no se puede revertir fácilmente),
 *  pero las eliminaciones de archivos se registran en $archivosAEliminar[]
 *  y se ejecutan DESPUÉS de que la transacción de BD se confirme con éxito.
 *  Esto previene la pérdida de archivos ante un fallo de BD.
 *
 * SEGURIDAD XSS:
 *  sanitizarContenido() usa DOMDocument para filtrar etiquetas y atributos
 *  peligrosos. Trix ya sanitiza en el frontend; esta es la segunda línea de
 *  defensa en el servidor (defensa en profundidad).
 */
class NewsFormService
{
    use SanitizesRichText;

    public function __construct(
        private readonly NewsFormRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  LECTURA — DATOS PARA EL FORMULARIO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve el array de [id => nombre] de categorías disponibles.
     *
     * @return array<int, string>
     */
    public function obtenerCategorias(): array
    {
        return $this->repository->availableCategories()
            ->pluck('name', 'id')
            ->toArray();
    }

    /**
     * Carga todos los datos de la noticia para pre-rellenar el formulario de edición.
     *
     * OPTIMIZACIóN N+1: el eager loading de 'media' con orderBy se ejecuta
     * en una sola query adicional, evitando consultas individuales por cada medio.
     *
     * COMPATIBILIDAD LEGACY: los medios con collection=null se clasifican
     * por tipo MIME para mantener compatibilidad con registros anteriores a
     * la migración add_collection_to_media_table.
     *
     * @return array{
     *   titulo: string, resumen: string, contenido: string,
     *   categoriaId: int, fechaPublicacion: string,
     *   portadaUrl: string|null, autorNombre: string,
     *   mediosSlider: array, documentos: array
     * }
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function obtenerParaEdicion(int $id): array
    {
        $noticia = $this->repository->findForEdit($id);

        // Colección de todos los medios ya cargados (eager load, sin queries adicionales)
        $todosLosMedias = $noticia->media;

        // ── Portada ──────────────────────────────────────────────────────────
        // Preferencia: collection='cover'. Fallback: primera imagen sin clasificar (legacy).
        $portada = $todosLosMedias->firstWhere('collection', 'cover')
            ?? $todosLosMedias->first(fn ($m) => $m->collection === null && $m->isImage());

        // ── Medios del slider ─────────────────────────────────────────────────
        // Preferencia: collection='slider'. Fallback: imágenes/vídeos sin clasificar (legacy).
        $slider = $todosLosMedias
            ->filter(fn ($m) => $m->collection === 'slider'
                // Fallback legacy: medio sin collection que sea imagen o vídeo y no sea la portada
                || ($m->collection === null && ($m->isImage() || $m->isVideo()) && $m !== $portada)
            )
            ->sortBy('order')
            ->map(fn ($m) => [
                'id'     => $m->id,
                'url'    => $m->url(),
                'tipo'   => $m->isImage() ? 'imagen' : 'video',
                'nombre' => $m->title ?? basename($m->path),
            ])
            ->values()
            ->toArray();

        // ── Documentos descargables ────────────────────────────────────────────
        // Preferencia: collection='document'. Fallback: archivos sin clasificar que no son media (legacy).
        $documentos = $todosLosMedias
            ->filter(fn ($m) => $m->collection === 'document'
                // Fallback legacy: medio sin collection que sea documento (ni imagen ni vídeo)
                || ($m->collection === null && $m->isDocument())
            )
            ->map(fn ($m) => [
                'id'     => $m->id,
                'nombre' => $m->title ?? basename($m->path),
                'url'    => $m->url(),
            ])
            ->values()
            ->toArray();

        return [
            'titulo'            => $noticia->title,
            'resumen'           => $noticia->summary ?? '',
            // toHtml(): fragmento HTML "crudo" (sin wrapper <div class="trix-content">),
            // tal cual lo espera el valor inicial del editor Trix.
            'contenido'         => $noticia->content->toHtml(),
            'categoriaId'       => $noticia->category_id,
            'fechaPublicacion'  => $noticia->published_at
                ? $noticia->published_at->format('Y-m-d')
                : now()->format('Y-m-d'),
            'portadaUrl'        => $portada?->url(),
            'autorNombre'       => $noticia->author?->name ?? $noticia->author_name ?? '',
            'mediosSlider'      => $slider,
            'documentos'        => $documentos,
            // Marca inmutable de primera publicación — null implica borrador nunca publicado
            'firstPublishedAt'  => $noticia->first_published_at,
            // Estado actual para controlar la interfaz del formulario
            'estadoActual'      => $noticia->status,
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — GUARDADO PRINCIPAL
    // ════════════════════════════════════════════════════════════════════

    /**
     * Crea o actualiza una noticia con todos sus archivos asociados.
     *
     * FLUJO:
     *  1. Abrir transacción DB.
     *  2. Crear / actualizar el registro News.
     *  3. Gestionar portada (eliminar antigua + guardar nueva).
     *  4. Eliminar medios del slider marcados para borrar.
     *  5. Guardar nuevos medios del slider.
     *  6. Eliminar documentos marcados para borrar.
     *  7. Guardar nuevos documentos.
     *  8. Confirmar transacción.
     *  9. Eliminar archivos físicos post-transacción (no revertibles).
     *
     * @param int|null         $noticiaId        null para crear, int para editar
     * @param array            $datos            campos del formulario validados
     * @param UploadedFile|null $portada          nueva imagen de portada (puede ser null)
     * @param bool             $eliminarPortada  true si el admin quitó la portada actual
     * @param array            $mediosNuevos     array de UploadedFile para el slider
     * @param int[]            $mediosAEliminar  IDs de medios slider a borrar
     * @param array            $documentosNuevos array de UploadedFile para documentos
     * @param int[]            $documentosAEliminar IDs de documentos a borrar
     *
     * @throws \Throwable si la transacción de BD falla
     */
    public function guardar(
        ?int          $noticiaId,
        array         $datos,
        ?UploadedFile $portada,
        bool          $eliminarPortada,
        array         $mediosNuevos,
        array         $mediosAEliminar,
        array         $documentosNuevos,
        array         $documentosAEliminar,
        bool          $fechaBloqueada = false, // true cuando el artículo fue publicado alguna vez
    ): News {
        // Archivos físicos a eliminar DESPUÉS de que la transacción tenga éxito
        $archivosAEliminar = [];

        $noticia = DB::transaction(function () use (
            $noticiaId, $datos, $portada, $eliminarPortada,
            $mediosNuevos, $mediosAEliminar, $documentosNuevos, $documentosAEliminar,
            $fechaBloqueada, &$archivosAEliminar
        ) {
            // ── 1. Crear o actualizar la noticia ─────────────────────────
            $atributos = [
                'title'       => trim($datos['titulo']),
                'summary'     => trim($datos['resumen']) ?: null,
                'content'     => $this->sanitizarContenido($datos['contenido']),
                // Borrador sin categoría seleccionada → NULL (columna nullable,
                // ver migración make_category_id_nullable_on_news_table).
                // Publicación siempre trae una categoría válida —garantizado
                // por NewsForm::reglasPublicacion()—.
                'category_id' => $datos['categoriaId'] !== '' ? (int) $datos['categoriaId'] : null,
                'status'      => $datos['estado'],
            ];

            // Solo actualizar published_at si la noticia nunca fue publicada.
            // Si fechaBloqueada=true el artículo ya tuvo vida pública y su fecha
            // original queda intacta (solo se actualizará updated_at).
            if (! $fechaBloqueada) {
                $atributos['published_at'] = Carbon::parse($datos['fechaPublicacion']);
            }

            if ($noticiaId === null) {
                // En creación: asignar autor desde la sesión autenticada
                $atributos['author_id']   = $datos['autorId'];
                $atributos['author_name'] = $datos['autorNombre'];

                // Marcar primera publicación si el estado inicial ya es 'published'
                if ($datos['estado'] === 'published') {
                    $atributos['first_published_at'] = now();
                }

                $noticia = $this->repository->create($atributos);
            } else {
                // Registrar first_published_at solo la primera vez que se publica.
                // Una vez escrito nunca se sobrescribe (invariante de inmutabilidad).
                if (! $fechaBloqueada && $datos['estado'] === 'published') {
                    $atributos['first_published_at'] = now();
                }

                // En edición: el autor original no se modifica
                $this->repository->update($noticiaId, $atributos);
                // Recargar para tener el modelo con el ID correcto
                $noticia = $this->repository->findForEdit($noticiaId);
            }

            // ── 2. Gestionar portada ──────────────────────────────────────
            if ($eliminarPortada || $portada !== null) {
                // Registrar archivos físicos de la portada actual para borrar post-commit
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->recopilarRutasDeColeccion($noticia->id, 'cover')
                );
                $this->repository->deleteMediaByCollection($noticia->id, 'cover');
            }

            if ($portada !== null) {
                $ruta = $portada->store('news/covers', 'public');
                $noticia->media()->create([
                    'collection' => 'cover',
                    'disk'       => 'public',
                    'path'       => $ruta,
                    'mime'       => $portada->getMimeType(),
                    'size'       => $portada->getSize(),
                    'title'      => $datos['titulo'],
                    'alt'        => $datos['titulo'],
                    'order'      => 0,
                ]);
            }

            // ── 3. Eliminar medios slider marcados ────────────────────────
            if (! empty($mediosAEliminar)) {
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->recopilarRutas($mediosAEliminar)
                );
                $this->repository->deleteMediaByIds($mediosAEliminar);
            }

            // ── 4. Guardar nuevos medios del slider ───────────────────────
            if (! empty($mediosNuevos)) {
                $orden = $this->repository->maxSliderOrder($noticia->id);
                foreach ($mediosNuevos as $archivo) {
                    $ruta = $archivo->store('news/media', 'public');
                    $orden++;
                    $noticia->media()->create([
                        'collection' => 'slider',
                        'disk'       => 'public',
                        'path'       => $ruta,
                        'mime'       => $archivo->getMimeType(),
                        'size'       => $archivo->getSize(),
                        'title'      => $archivo->getClientOriginalName(),
                        'alt'        => $archivo->getClientOriginalName(),
                        'order'      => $orden,
                    ]);
                }
            }

            // ── 5. Eliminar documentos marcados ───────────────────────────
            if (! empty($documentosAEliminar)) {
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->recopilarRutas($documentosAEliminar)
                );
                $this->repository->deleteMediaByIds($documentosAEliminar);
            }

            // ── 6. Guardar nuevos documentos ──────────────────────────────
            foreach ($documentosNuevos as $archivo) {
                $ruta = $archivo->store('news/documents', 'public');
                $noticia->media()->create([
                    'collection' => 'document',
                    'disk'       => 'public',
                    'path'       => $ruta,
                    'mime'       => $archivo->getMimeType(),
                    'size'       => $archivo->getSize(),
                    'title'      => $archivo->getClientOriginalName(),
                    'alt'        => null,
                    'order'      => null,
                ]);
            }

            return $noticia;
        });

        // ── 7. Eliminar archivos físicos TRAS confirmar la transacción ────
        // Se ejecuta aquí y no dentro de la transacción para evitar borrar
        // archivos si la BD hace rollback.
        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            \Illuminate\Support\Facades\Storage::disk($disk)->delete($path);
        }

        return $noticia;
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve un array de ['disk' => ..., 'path' => ...] para los IDs dados.
     * Se usa para registrar qué archivos físicos eliminar post-transacción.
     *
     * @param  int[]                              $ids
     * @return array<int, array{disk: string, path: string}>
     */
    private function recopilarRutas(array $ids): array
    {
        return \App\Models\Media::whereIn('id', $ids)
            ->get(['disk', 'path'])
            ->map(fn ($m) => ['disk' => $m->disk, 'path' => $m->path])
            ->toArray();
    }

    /**
     * Devuelve rutas de todos los medios de una colección para una noticia.
     *
     * @return array<int, array{disk: string, path: string}>
     */
    private function recopilarRutasDeColeccion(int $newsId, string $collection): array
    {
        return \App\Models\Media::where('mediable_type', (new News)->getMorphClass())
            ->where('mediable_id', $newsId)
            ->where('collection', $collection)
            ->get(['disk', 'path'])
            ->map(fn ($m) => ['disk' => $m->disk, 'path' => $m->path])
            ->toArray();

    }
}
