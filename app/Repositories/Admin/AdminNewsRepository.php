<?php

namespace App\Repositories\Admin;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Media;
use App\Models\News;
use App\Models\Scopes\PublishedScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repositorio de noticias para el panel de administración.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD relacionada
 * con la gestión administrativa de News. A diferencia del repositorio público,
 * expone TODOS los estados (draft, published, archived).
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - select() explícito en la paginación → 'content' nunca viaja al listado.
 *  - Inputs sanitizados antes de usarse en cláusulas WHERE.
 *  - Columna y dirección de orden validadas contra lista blanca → sin inyección de ORDER BY.
 *  - Filtro de estado validado contra News::STATUSES → sin bypass por valores arbitrarios.
 *
 * OPTIMIZACIÓN:
 *  - with() eager loading para 'media' y 'category' → previene N+1 en el grid de tarjetas.
 *  - withCount('comments') agrega el conteo en la query principal → sin query extra por fila.
 *  - select() explícito con columnas mínimas necesarias → menor transferencia de datos.
 */
class AdminNewsRepository
{
    // ── Columnas mínimas para el listado administrativo ──────────────────
    // 'content' está deliberadamente ausente — solo se carga en la vista de detalle.
    private const LIST_COLUMNS = [
        'id',
        'uuid',
        'title',
        'summary',
        'author_name',
        'category_id',
        'status',
        'published_at',
        'views_count',
        'created_at',
        'updated_at',
    ];

    // ── Lista blanca de columnas y dirección de ordenamiento ─────────────
    // Previene inyección de ORDER BY con valores arbitrarios del cliente.
    private const SORT_MAP = [
        'recientes'    => ['created_at',  'desc'],
        'mas-vistas'   => ['views_count', 'desc'],
        'menos-vistas' => ['views_count', 'asc'],
    ];

    private const MAX_SEARCH_LENGTH   = 100;
    private const MAX_CATEGORY_LENGTH = 120;

    // ════════════════════════════════════════════════════════════════════
    //  CONSULTAS PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginación de noticias con todos los filtros administrativos aplicados.
     *
     * Incluye eager loading de media, category y conteo de comentarios
     * para evitar el problema N+1 en el grid de tarjetas.
     */
    public function paginate(
        string $search,
        string $status,
        string $category,
        string $sortBy,
        int    $perPage = 12
    ): LengthAwarePaginator {
        return $this->buildQuery($search, $status, $category, $sortBy)
            ->paginate($perPage, self::LIST_COLUMNS);
    }

    /**
     * Conteo de noticias en estado 'published' para la métrica del panel.
     * No aplica los filtros de búsqueda — muestra siempre el total real.
     *
     * NOTA: withoutGlobalScope() es necesario porque el Global Scope PublishedScope
     * filtraría automáticamente a solo publicadas, pero aquí necesitamos el control
     * explícito del WHERE para claridad semántica. Se mantiene el opt-out por
     * consistencia y para que este método siga funcionando si la lógica del scope cambia.
     */
    public function countPublished(): int
    {
        // Opt-out del Global Scope: el admin necesita contar solo publicadas,
        // pero con conocimiento explícito — no por efecto del scope público.
        return News::withoutGlobalScope(PublishedScope::class)
            ->where('status', 'published')
            ->count();
    }

    /**
     * Lista de categorías tipo 'news' que tienen al menos una noticia asignada.
     * Usada para construir el selector de filtro de categoría.
     *
     * NOTA: el whereHas('news') comprueba existencia de cualquier noticia
     * (de cualquier estado), lo que es correcto para el admin. Se usa
     * withoutGlobalScope dentro del callback del whereHas para que la
     * sub-query de existencia no sea filtrada por el scope público.
     *
     * @return Collection<int, string>
     */
    public function availableCategories(): Collection
    {
        return Category::where('type', 'news')
            // Sin opt-out en la query de Category (no es News).
            // El whereHas verifica cualquier noticia — todos los estados.
            ->whereHas('news', fn ($q) => $q->withoutGlobalScope(PublishedScope::class))
            ->orderBy('name')
            ->pluck('name');
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BUILDER
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye la query base con eager loading, filtros y ordenamiento.
     *
     * OPT-OUT DEL GLOBAL SCOPE:
     *  withoutGlobalScope(PublishedScope::class) es imprescindible aquí.
     *  Sin él, el Global Scope filtraría automáticamente a status='published',
     *  haciendo invisibles los borradores, archivadas y deshabilitadas para
     *  el administrador. La intención explícita es ver TODOS los estados.
     *
     * NOTAS DE OPTIMIZACIÓN:
     *  - with('media:...') → carga solo columnas necesarias para portada y conteo.
     *  - with('category:id,name') → evita SELECT * en la tabla categories.
     *  - withCount('comments') → agrega comments_count en la misma query de News.
     *  - whereHas en categoría solo se aplica si el filtro está activo.
     */
    private function buildQuery(
        string $search,
        string $status,
        string $category,
        string $sortBy,
    ): Builder {
        // Opt-out explícito: el admin debe ver todos los estados de las noticias.
        $query = News::withoutGlobalScope(PublishedScope::class)
            ->with([
                // Carga solo columnas necesarias para portada y clasificación por mime
                'media:id,mediable_id,mediable_type,path,disk,mime,alt,title',
                // Solo id y nombre de categoría para el badge
                'category:id,name',
            ])
            ->withCount('comments');

        // ── Búsqueda por texto en título o resumen ───────────────────────
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('title',   'like', '%' . $term . '%')
                  ->orWhere('summary', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro por estado: validado contra News::STATUSES ────────────
        if ($status !== 'todas' && in_array($status, News::STATUSES, strict: true)) {
            $query->where('status', $status);
        }

        // ── Filtro por categoría: whitelist implícita contra BD ──────────
        $cat = $this->sanitize($category, self::MAX_CATEGORY_LENGTH);
        if ($cat !== 'todas' && $cat !== '') {
            $query->whereHas(
                'category',
                fn (Builder $q) => $q->where('name', $cat)
            );
        }

        // ── Ordenamiento contra lista blanca SORT_MAP ────────────────────
        [$column, $direction] = self::SORT_MAP[$sortBy] ?? self::SORT_MAP['recientes'];
        $query->orderBy($column, $direction);

        return $query;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MUTACIONES DE ESTADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Actualiza el estado de una noticia y fija updated_at en el instante actual.
     *
     * RENDIMIENTO:
     *  - UPDATE directo por PK (índice clustered) → una sola query O(log n).
     *  - Sin instanciar el modelo ni cargar relaciones → mínima memoria.
     *  - updated_at explícito asegura trazabilidad con timestamps manuales.
     *
     * SEGURIDAD:
     *  - whereIn(News::STATUSES) valida que el estado actual pertenezca al enum
     *    definido en el modelo; si no, el UPDATE afecta 0 filas (fail-safe).
     *  - $newStatus ya viene validado desde el servicio antes de llegar aquí.
     *  - Eloquent usa PDO prepared statements → inmune a SQL Injection.
     *
     * OPT-OUT DEL GLOBAL SCOPE:
     *  withoutGlobalScope() es necesario porque el UPDATE sin él solo afectaría
     *  noticias publicadas (el scope añade WHERE status='published'), lo que
     *  impediría deshabilitar una noticia ya deshabilitada o en borrador.
     */
    public function toggleStatus(int $id, string $newStatus): void
    {
        // Opt-out explícito: el toggle debe funcionar en CUALQUIER estado actual.
        News::withoutGlobalScope(PublishedScope::class)
            ->where('id', $id)
            ->whereIn('status', News::STATUSES)
            ->update([
                'status'     => $newStatus,
                'updated_at' => now(),
            ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ELIMINACIÓN PERMANENTE EN CASCADA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina en cascada una noticia y TODOS sus registros relacionados.
     *
     * Debe invocarse dentro de una transacción de BD (ver AdminNewsService::eliminarNoticia)
     * y únicamente después de confirmar que la noticia existe.
     *
     * ARQUITECTURA — eliminación a nivel de BD en lugar de eventos de modelo:
     *  Se usan sentencias DELETE masivas (whereIn / where) en vez de cargar e
     *  iterar modelos uno a uno o depender de observers/eventos 'deleting'.
     *  Razones:
     *   1. RENDIMIENTO: una sola sentencia SQL por tabla, sin instanciar miles
     *      de modelos en memoria — crítico para hilos de comentarios masivos.
     *   2. PREDECIBILIDAD: el orden de borrado es explícito y auditable aquí,
     *      sin lógica oculta dispersa en observers que sea fácil de olvidar.
     *   3. ATOMICIDAD: todo ocurre dentro de la misma transacción que envuelve
     *      este método, garantizando rollback completo ante cualquier fallo.
     *
     * RENDIMIENTO:
     *  - Las rutas físicas se recopilan ANTES de borrar los registros de media,
     *    para poder eliminar los archivos del disco DESPUÉS del commit.
     *  - whereIn por columnas indexadas (mediable_id/commentable_id) → O(log n).
     *
     * @return array<int, array{disk: string, path: string}> Rutas físicas a borrar tras el commit
     */
    public function deleteNewsWithCascade(int $id): array
    {
        // El morph map registra 'news' como alias corto de App\Models\News
        $morphClass = (new News)->getMorphClass();

        // ── 1. Recopilar rutas físicas de TODOS los adjuntos antes de borrarlos ──
        // (imágenes, videos y documentos viven todos en la tabla 'media')
        $archivos = Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $id)
            ->get(['disk', 'path'])
            ->map(fn (Media $media) => ['disk' => $media->disk, 'path' => $media->path])
            ->all();

        // ── 2. Eliminar comentarios en una sola sentencia DELETE masiva ──────
        // Sin cargar modelos en memoria: eficiente incluso con hilos masivos.
        Comment::where('commentable_type', $morphClass)
            ->where('commentable_id', $id)
            ->delete();

        // ── 3. Eliminar registros de media (los archivos físicos se borran
        //      después del commit, ver AdminNewsService::eliminarNoticia) ────
        Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $id)
            ->delete();

        // ── 4. Eliminar la noticia — opt-out del scope: debe poder borrarse
        //      en cualquier estado (draft, published, archived, disabled) ───
        News::withoutGlobalScope(PublishedScope::class)
            ->where('id', $id)
            ->delete();

        return $archivos;
    }

    // ════════════════════════════════════════════════════════════════════
    //  SANITIZACIÓN DE INPUTS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina HTML y limita longitud.
     * Aunque Eloquent usa PDO bindings, strip_tags previene payloads de salida
     * que podrían afectar logs o mensajes de error.
     */
    private function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $maxLength);
    }
}
