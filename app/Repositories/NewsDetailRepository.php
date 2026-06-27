<?php

namespace App\Repositories;

use App\Models\News;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio para la página de detalle de noticias.
 *
 * SEGURIDAD:
 *  - Toda condición WHERE usa Eloquent (PDO prepared statements).
 *  - El scope published() garantiza que solo noticias publicadas sean accesibles.
 *  - UUID validado externamente antes de llegar aquí (NewsDetailService).
 *  - select() explícito: nunca se traen columnas innecesarias.
 */
class NewsDetailRepository
{
    // Columnas necesarias para el detalle completo
    private const DETAIL_COLUMNS = [
        'id',
        'uuid',
        'title',
        'summary',
        'content',      // ← aquí sí se incluye el contenido completo
        'author_name',
        'category_id',
        'published_at',
    ];

    // Columnas necesarias para el sidebar (sin content)
    private const SIDEBAR_COLUMNS = [
        'id',
        'uuid',
        'title',
        'category_id',
        'published_at',
    ];

    /**
     * Busca una noticia publicada por su UUID.
     * Retorna null si no existe o no está publicada → sin distinción de casos
     * para prevenir enumeración de recursos.
     */
    public function findPublishedByUuid(string $uuid): ?News
    {
        $news = News::published()
            ->select(self::DETAIL_COLUMNS)
            ->with([
                // Todos los media ordenados — el DTO clasificará por collection y mime
                'media' => fn ($q) => $q
                    ->select(['id', 'mediable_id', 'mediable_type',
                              'collection', 'path', 'disk', 'mime', 'size', 'alt', 'title', 'order'])
                    ->orderBy('order'),
                'category:id,name',
            ])
            ->where('uuid', $uuid)
            ->first();

        if ($news !== null) {
            // Incremento atómico: UPDATE news SET views_count = views_count + 1 WHERE id = ?
            // Usar DB::table() evita cargar el modelo nuevamente y es thread-safe.
            // El scope published() ya validó que la noticia existe y está publicada.
            DB::table('news')
                ->where('id', $news->id)
                ->increment('views_count');
        }

        return $news;
    }

    /**
     * Últimas N noticias publicadas para el sidebar.
     * Excluye la noticia actual para no repetirla.
     *
     * @param  string $excludeUuid  UUID de la noticia que se está leyendo.
     * @param  int    $limit        Máximo de noticias a retornar (default 3).
     */
    public function latestForSidebar(string $excludeUuid, int $limit = 3): Collection
    {
        return News::published()
            ->select(self::SIDEBAR_COLUMNS)
            ->with([
                // Solo portada (collection='cover') para el thumbnail del sidebar
                'media' => fn ($q) => $q
                    ->select(['id', 'mediable_id', 'mediable_type',
                              'collection', 'path', 'disk', 'mime', 'alt', 'title', 'order', 'size'])
                    ->where('collection', 'cover'),
                'category:id,name',
            ])
            ->where('uuid', '!=', $excludeUuid)
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}
