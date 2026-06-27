<?php

namespace App\Repositories\Admin;

use App\Models\Category;
use App\Models\Media;
use App\Models\News;
use App\Models\Scopes\PublishedScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Repositorio exclusivo del formulario de creación/edición de noticias.
 *
 * RESPONSABILIDAD ÚNICA:
 *  Encapsular toda interacción con la BD necesaria para el formulario AdminNewsForm.
 *  Separado de AdminNewsRepository para no mezclar responsabilidades de listado
 *  con las de mutación de datos y gestión de archivos.
 *
 * SEGURIDAD:
 *  - Toda consulta usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - withoutGlobalScope() explícito donde se requiere visibilidad de todos los estados.
 *  - Los archivos se eliminan en memoria antes de persistir el DELETE en BD.
 */
class NewsFormRepository
{
    // ════════════════════════════════════════════════════════════════════
    //  LECTURA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Carga la noticia completa para pre-rellenar el formulario de edición.
     *
     * Eager loading de todas las relaciones necesarias en una sola query compuesta:
     *  - 'media'             → portada, slider y documentos del formulario.
     *  - 'category:id,name'  → para poblar el select de categoría.
     *  - 'author:id,name'    → para mostrar el autor original (solo lectura en edición).
     *
     * withoutGlobalScope(): el admin debe poder editar noticias en cualquier estado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findForEdit(int $id): News
    {
        return News::withoutGlobalScope(PublishedScope::class)
            ->with([
                'media',
                'category:id,name',
                'author:id,name',
            ])
            ->findOrFail($id);
    }

    /**
     * Categorías disponibles para el select del formulario.
     * Solo categorías de tipo 'news', ordenadas alfabéticamente.
     *
     * @return Collection<int, Category>
     */
    public function availableCategories(): Collection
    {
        return Category::where('type', 'news')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — NOTICIA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Inserta una nueva noticia y devuelve el modelo con id y uuid generados.
     * UUID se asigna automáticamente por el boot() del modelo News.
     */
    public function create(array $attrs): News
    {
        return News::create($attrs);
    }

    /**
     * Actualiza campos de la noticia por su PK.
     * withoutGlobalScope(): debe actualizar noticias en cualquier estado.
     */
    public function update(int $id, array $attrs): void
    {
        News::withoutGlobalScope(PublishedScope::class)
            ->where('id', $id)
            ->update($attrs + ['updated_at' => now()]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — MEDIA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina registros de media de la BD y sus archivos del disco.
     * Los archivos se recopilan ANTES de borrar en BD para no perder la ruta
     * si el borrado de BD falla (consistencia best-effort en el disco).
     *
     * @param int[] $ids IDs de los registros Media a eliminar
     */
    public function deleteMediaByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        // Cargar solo las columnas necesarias para la eliminación del archivo
        $items = Media::whereIn('id', $ids)->get(['id', 'disk', 'path']);

        // Eliminar archivos del disco antes de borrar los registros de BD
        foreach ($items as $item) {
            Storage::disk($item->disk)->delete($item->path);
        }

        Media::whereIn('id', $ids)->delete();
    }

    /**
     * Elimina todos los medios de una colección específica de una noticia.
     * Útil para reemplazar la portada al subir una nueva imagen de portada.
     *
     * @param int    $newsId     ID de la noticia
     * @param string $collection Nombre de la colección ('cover', 'slider', 'document')
     */
    public function deleteMediaByCollection(int $newsId, string $collection): void
    {
        $items = Media::where('mediable_type', (new News)->getMorphClass())
            ->where('mediable_id', $newsId)
            ->where('collection', $collection)
            ->get(['id', 'disk', 'path']);

        foreach ($items as $item) {
            Storage::disk($item->disk)->delete($item->path);
        }

        Media::where('mediable_type', (new News)->getMorphClass())
            ->where('mediable_id', $newsId)
            ->where('collection', $collection)
            ->delete();
    }

    /**
     * Obtiene el order máximo de los medios del slider para asignar orden correlativo.
     */
    public function maxSliderOrder(int $newsId): int
    {
        return (int) Media::where('mediable_type', (new News)->getMorphClass())
            ->where('mediable_id', $newsId)
            ->where('collection', 'slider')
            ->max('order');
    }
}
