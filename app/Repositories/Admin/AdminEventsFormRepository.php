<?php

namespace App\Repositories\Admin;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventCollaborator;
use App\Models\Media;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Repositorio exclusivo del formulario de creación/edición de eventos.
 *
 * RESPONSABILIDAD ÚNICA:
 *  Encapsular toda interacción con la BD necesaria para el formulario
 *  EventForm. Separado de AdminEventsRepository (listado) por la misma
 *  razón que separa NewsFormRepository de AdminNewsRepository: no mezclar
 *  responsabilidades de listado con las de mutación de datos y archivos.
 *
 * SEGURIDAD:
 *  - Toda consulta usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - Los archivos se eliminan del disco antes de persistir el DELETE en BD.
 */
class AdminEventsFormRepository
{
    // ════════════════════════════════════════════════════════════════════
    //  LECTURA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Carga el evento completo para pre-rellenar el formulario de edición.
     *
     * Event no tiene Global Scope de visibilidad (a diferencia de News), por
     * lo que no se necesita ningún withoutGlobalScope() aquí.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findForEdit(int $id): Event
    {
        return Event::with([
            'media',
            'category:id,name',
            // Eager loading anidado: una sola consulta batch por nivel
            // (colaboradores → socio → media del socio) evita el N+1 que
            // tendría resolver el logo de cada colaborador por separado.
            'collaborators.partner:id,name',
            'collaborators.partner.media:id,mediable_id,mediable_type,collection,path,disk,mime',
        ])->findOrFail($id);
    }

    /**
     * Categorías disponibles para el select del formulario.
     * Solo categorías de tipo 'events', ordenadas alfabéticamente.
     *
     * @return Collection<int, Category>
     */
    public function availableCategories(): Collection
    {
        return Category::forEvents()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — EVENTO
    // ════════════════════════════════════════════════════════════════════

    /** Inserta un nuevo evento y devuelve el modelo con id y uuid generados. */
    public function create(array $attrs): Event
    {
        return Event::create($attrs);
    }

    /** Actualiza campos del evento por su PK. */
    public function update(int $id, array $attrs): void
    {
        Event::where('id', $id)->update($attrs + ['updated_at' => now()]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — MEDIA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina registros de media de la BD y sus archivos del disco.
     * Las rutas se recopilan ANTES de borrar en BD para no perder el rastro
     * si el borrado de BD falla (consistencia best-effort en el disco).
     *
     * @param int[] $ids IDs de los registros Media a eliminar
     */
    public function deleteMediaByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $items = Media::whereIn('id', $ids)->get(['id', 'disk', 'path']);

        foreach ($items as $item) {
            Storage::disk($item->disk)->delete($item->path);
        }

        Media::whereIn('id', $ids)->delete();
    }

    /**
     * Elimina todos los medios de una colección específica de un evento.
     * Útil para reemplazar la portada al subir una nueva imagen.
     */
    public function deleteMediaByCollection(int $eventId, string $collection): void
    {
        $morphClass = (new Event)->getMorphClass();

        $items = Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $eventId)
            ->where('collection', $collection)
            ->get(['id', 'disk', 'path']);

        foreach ($items as $item) {
            Storage::disk($item->disk)->delete($item->path);
        }

        Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $eventId)
            ->where('collection', $collection)
            ->delete();
    }

    /** Obtiene el order máximo de los medios del slider para asignar orden correlativo. */
    public function maxSliderOrder(int $eventId): int
    {
        return (int) Media::where('mediable_type', (new Event)->getMorphClass())
            ->where('mediable_id', $eventId)
            ->where('collection', 'slider')
            ->max('order');
    }

    /**
     * Actualiza el campo 'order' de varios medios existentes tras un
     * reordenamiento por arrastrar y soltar en el slider.
     *
     * @param array<int, int> $idToOrderMap [mediaId => nuevoOrden]
     */
    public function updateMediaOrder(array $idToOrderMap): void
    {
        foreach ($idToOrderMap as $mediaId => $order) {
            Media::where('id', $mediaId)->update(['order' => $order]);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — COLABORADORES INVITADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Rutas de logotipo personalizado actualmente almacenadas para un
     * evento (solo colaboradores externos, is_custom = true). Se recopilan
     * ANTES de deleteCollaborators() para poder borrar del disco solo las
     * que el nuevo guardado ya no reutiliza — ver AdminEventsFormService::guardar().
     *
     * @return string[]
     */
    public function customCollaboratorLogoPaths(int $eventId): array
    {
        return EventCollaborator::where('event_id', $eventId)
            ->whereNotNull('custom_logo_path')
            ->pluck('custom_logo_path')
            ->all();
    }

    /**
     * Elimina todas las filas de colaboradores de un evento (no toca
     * archivos físicos — eso lo hace el servicio tras confirmar la
     * transacción). Paso previo a insertCollaborators(): cada guardado
     * reemplaza la lista completa en vez de calcular un diff fila a fila,
     * ya que el tamaño de esta lista es siempre pequeño por evento.
     */
    public function deleteCollaborators(int $eventId): void
    {
        EventCollaborator::where('event_id', $eventId)->delete();
    }

    /**
     * Inserta el conjunto final de colaboradores de un evento en una sola
     * operación masiva. insert() (no create()) se usa a propósito: evita
     * una consulta por fila y no dispara eventos de modelo, innecesarios
     * para un reemplazo total ya envuelto en una transacción.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public function insertCollaborators(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        EventCollaborator::insert($rows);
    }
}
