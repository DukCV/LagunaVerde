<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\News;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repositorio de noticias.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD
 * relacionada con News. Ningún componente o servicio construye
 * queries directamente.
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent → PDO prepared statements. Sin SQL Injection.
 *  - select() explícito en todos los métodos → 'content' nunca viaja al frontend.
 *  - Búsqueda con LIKE usa bindings internos de Eloquent → seguro.
 *  - Filtro de categoría valida contra valores existentes en BD → sin bypass.
 *  - Los resultados siempre filtran por scope 'published' → sin drafts expuestos.
 */
class NewsRepository
{
    // ── Columnas permitidas en SELECT para la vista de resumen ───────────
    // 'content' está deliberadamente ausente.
    private const SUMMARY_COLUMNS = [
        'id',            // requerido por Eloquent para relaciones
        'uuid',          // identificador público
        'title',
        'summary',
        'author_name',
        'category_id',
        'published_at',
    ];

    // ── Longitudes máximas de input para búsqueda ────────────────────────
    private const MAX_SEARCH_LENGTH   = 100;
    private const MAX_CATEGORY_LENGTH = 120;

    // ════════════════════════════════════════════════════════════════════
    //  CONSULTAS PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Noticia destacada: la más reciente que coincide con los filtros.
     * Retorna null si no hay resultados.
     */
    public function findFeatured(string $search, string $categoryName): ?News
    {
        return $this->baseQuery($search, $categoryName)->first();
    }

    /**
     * Paginación del resto de noticias (excluye la destacada por UUID).
     *
     * @param  string|null $excludeUuid  UUID de la noticia destacada a excluir.
     * @param  int         $perPage      Registros por página (default 9).
     */
    public function paginateExcluding(
        string  $search,
        string  $categoryName,
        ?string $excludeUuid,
        int     $perPage = 9
    ): LengthAwarePaginator {
        $query = $this->baseQuery($search, $categoryName);

        if ($excludeUuid !== null) {
            $query->where('uuid', '!=', $excludeUuid);
        }

        return $query->paginate($perPage, self::SUMMARY_COLUMNS);
    }

    /**
     * Conteo total de resultados publicados con los filtros aplicados.
     * Útil para mostrar "N resultados".
     */
    public function countPublished(string $search, string $categoryName): int
    {
        return $this->baseQuery($search, $categoryName)->count();
    }

    /**
     * Últimas N noticias publicadas para la sección de noticias del home.
     *
     * CENTRALIZACIÓN (principio DRY):
     *  Este método es la única fuente de verdad para la query del home.
     *  Anteriormente duplicada en NewsSection::fetchLatestNews(). Al
     *  moverla aquí, cualquier cambio en columnas, relaciones o límite
     *  se propaga automáticamente sin tocar el componente Livewire.
     *
     * SEGURIDAD Y RENDIMIENTO:
     *  - select() explícito: 'content' nunca llega a la vista del home.
     *  - scope published() garantiza status='published' AND published_at<=now().
     *  - with() eager load: previene el problema N+1 para media y category.
     *  - Solo la imagen de portada (order=0): una sola imagen por noticia.
     *  - limit($limit): acota el conjunto de resultados → sin volcados masivos.
     *
     * @param  int            $limit  Máximo de noticias a retornar.
     * @return Collection<int, News>
     */
    public function latestForHome(int $limit = 3): Collection
    {
        return News::published()
            ->select([
                'id',            // requerido por Eloquent para resolver relaciones
                'uuid',          // identificador público — nunca el ID entero
                'title',
                'summary',
                'author_name',
                'category_id',
                'published_at',
            ])
            ->with([
                // Solo la imagen de portada (collection='cover') — una query extra para todas las noticias
                'media' => fn ($q) => $q
                    ->select(['id', 'mediable_id', 'mediable_type',
                              'collection', 'path', 'disk', 'alt', 'title', 'order', 'mime', 'size'])
                    ->where('collection', 'cover'),

                // Solo nombre de la categoría — evita SELECT * en la tabla categories
                'category:id,name',
            ])
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Lista de categorías disponibles para el filtro del toolbar.
     * Solo categorías del tipo 'news' que tengan al menos una noticia publicada.
     *
     * @return Collection<int, string>  Colección de nombres de categoría.
     */
    public function availableCategories(): Collection
    {
        return Category::where('type', 'news')
            ->whereHas('news', fn ($q) => $q->published())
            ->orderBy('name')
            ->pluck('name');
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BASE — reutilizada por todos los métodos públicos
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye la query base con filtros de búsqueda y categoría.
     *
     * SEGURIDAD:
     *  - LIKE '%valor%': Eloquent enlaza el valor como parámetro PDO.
     *  - Categoría validada contra nombre real en BD; si no existe → sin filtro.
     *  - El scope published() garantiza status='published' AND published_at<=now().
     */
    private function baseQuery(string $search, string $categoryName): Builder
    {
        $query = News::published()
            ->select(self::SUMMARY_COLUMNS)
            ->with([
                // Portada y documentos en eager load — una query adicional por tipo
                'media' => fn ($q) => $q->select([
                    'id', 'mediable_id', 'mediable_type',
                    'collection', 'path', 'disk', 'mime', 'size', 'alt', 'title', 'order',
                ]),
                'category:id,name',
            ])
            ->latest('published_at');

        // ── Búsqueda por texto ───────────────────────────────────────────
        $term = $this->sanitizeSearch($search);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('title',   'like', '%' . $term . '%')
                  ->orWhere('summary', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro por categoría (whitelist contra BD) ───────────────────
        $category = $this->sanitizeCategory($categoryName);
        if ($category !== 'all' && $category !== '') {
            $exists = Category::where('type', 'news')
                               ->where('name', $category)
                               ->exists();
            if ($exists) {
                $query->whereHas(
                    'category',
                    fn ($q) => $q->where('name', $category)
                );
            }
            // Si la categoría no existe en BD → se ignora el filtro (sin error)
        }

        return $query;
    }

    // ════════════════════════════════════════════════════════════════════
    //  SANITIZACIÓN DE INPUTS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Limpia el término de búsqueda: elimina HTML y limita longitud.
     * Aunque LIKE con bindings es seguro, strip_tags previene payload de salida.
     */
    private function sanitizeSearch(string $value): string
    {
        return mb_substr(strip_tags(trim($value)), 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * Limita la longitud del nombre de categoría recibido desde el componente.
     */
    private function sanitizeCategory(string $value): string
    {
        return mb_substr(strip_tags(trim($value)), 0, self::MAX_CATEGORY_LENGTH);
    }
}
