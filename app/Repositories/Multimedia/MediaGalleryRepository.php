<?php

namespace App\Repositories\Multimedia;

use App\Models\Event;
use App\Models\News;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * Repositorio de la galería multimedia.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD
 * relacionada con la galería.  Ningún servicio ni componente Livewire
 * construye queries directamente.
 *
 * ESTRATEGIA DE CONSULTA (optimizada para datasets grandes):
 *  - Fase 1 — índice ligero: une News y Events vía UNION ALL, seleccionando
 *    sólo (id, tipo, fecha de orden). El ORDER BY + LIMIT/OFFSET ocurren en
 *    SQL, no en PHP, por lo que SIEMPRE se cargan como máximo $perPage filas
 *    en memoria, sin importar cuántas noticias/eventos existan en total.
 *  - Fase 2 — hidratación: con los IDs ya acotados de la página actual, se
 *    cargan los modelos completos (con su media filtrada vía with()) y se
 *    reordenan según el orden ya resuelto por el UNION.
 *  - Cada modelo sólo expone los álbumes que tienen al menos un item
 *    de media que coincide con el filtro de tipo (whereHas).
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent → PDO prepared statements.  Sin SQL inyectado.
 *  - select() explícito → "content" nunca viaja al frontend.
 *  - strip_tags + mb_substr en sanitización de inputs.
 *  - Solo se devuelven registros con status='published'.
 *  - El filtro MIME se valida contra una whitelist antes de aplicarse.
 */
class MediaGalleryRepository
{
    // ── Columnas permitidas en SELECT para News ───────────────────────────
    // "content" está deliberadamente ausente para minimizar el payload
    private const NEWS_COLUMNS = [
        'id', 'uuid', 'title', 'summary', 'published_at',
    ];

    // ── Columnas permitidas en SELECT para Events ─────────────────────────
    private const EVENT_COLUMNS = [
        'id', 'uuid', 'name', 'description', 'start_at',
    ];

    // ── Columnas de media necesarias para la galería ──────────────────────
    private const MEDIA_COLUMNS = [
        'id', 'mediable_id', 'mediable_type',
        'disk', 'path', 'mime', 'title', 'alt', 'order',
    ];

    // ── Límites de longitud para inputs del usuario ───────────────────────
    private const MAX_SEARCH_LENGTH = 100;

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve un paginador de modelos Eloquent (News|Event) con media cargada.
     *
     * La paginación se realiza a nivel de álbum: cada página contiene
     * $perPage álbumes (noticias o eventos), no items de media individuales.
     *
     * @param  string  $search      Término de búsqueda libre (título / descripción).
     * @param  string  $typeFilter  'all' | 'image' | 'video'
     * @param  int     $perPage     Álbumes por página.
     * @param  int     $page        Número de página actual.
     */
    public function getAlbums(
        string $search,
        string $typeFilter,
        int    $perPage = 9,
        int    $page    = 1,
    ): LengthAwarePaginator {
        // Sanear inputs antes de cualquier uso en queries
        $term       = $this->sanitizeSearch($search);
        $mimePrefix = $this->resolveMimePrefix($typeFilter);

        // ── Fase 1: índice ligero vía UNION ALL, paginado en SQL ──────────
        $indexPage = $this->buildIndexQuery($term, $mimePrefix)
            ->paginate($perPage, ['*'], 'page', $page);

        // ── Fase 2: hidratar sólo los modelos de la página actual ─────────
        return $this->hydrate($indexPage, $mimePrefix, $perPage, $page);
    }

    // ════════════════════════════════════════════════════════════════════
    //  FASE 1 — ÍNDICE LIGERO (UNION ALL)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye la query de índice: une News y Events en un único conjunto
     * ordenable/paginable a nivel de SQL, seleccionando sólo (id, tipo, fecha).
     *
     * Al devolver un query builder "base" (no Eloquent), el resultado son
     * objetos stdClass — sin riesgo de hidratar columnas de un modelo con
     * datos de otro.
     */
    private function buildIndexQuery(string $term, ?string $mimePrefix): \Illuminate\Database\Query\Builder
    {
        $newsIndex = News::published()
            ->whereHas('media', fn ($q) => $this->applyMediaConstraints($q, $mimePrefix))
            ->when($term !== '', fn (Builder $q) =>
                // Búsqueda en título y resumen de la noticia
                $q->where(fn (Builder $inner) =>
                    $inner->where('title',   'like', '%' . $term . '%')
                          ->orWhere('summary', 'like', '%' . $term . '%')
                )
            )
            ->select(['id', DB::raw("'news' as source_type"), 'published_at as sort_date'])
            ->toBase();

        $eventsIndex = Event::published()
            ->whereHas('media', fn ($q) => $this->applyMediaConstraints($q, $mimePrefix))
            ->when($term !== '', fn (Builder $q) =>
                // Búsqueda en nombre y descripción del evento
                $q->where(fn (Builder $inner) =>
                    $inner->where('name',        'like', '%' . $term . '%')
                          ->orWhere('description', 'like', '%' . $term . '%')
                )
            )
            ->select(['id', DB::raw("'event' as source_type"), 'start_at as sort_date'])
            ->toBase();

        return $newsIndex->unionAll($eventsIndex)->orderByDesc('sort_date');
    }

    // ════════════════════════════════════════════════════════════════════
    //  FASE 2 — HIDRATACIÓN DE LA PÁGINA ACTUAL
    // ════════════════════════════════════════════════════════════════════

    /**
     * Carga los modelos completos (News/Event) correspondientes a los IDs
     * de la página actual del índice, y los reordena según el orden ya
     * resuelto por el UNION (no se puede confiar en el orden de whereIn()).
     */
    private function hydrate(LengthAwarePaginator $indexPage, ?string $mimePrefix, int $perPage, int $page): LengthAwarePaginator
    {
        $rows = collect($indexPage->items());

        $newsIds   = $rows->where('source_type', 'news')->pluck('id');
        $eventIds  = $rows->where('source_type', 'event')->pluck('id');

        $newsModels = $newsIds->isEmpty()
            ? collect()
            : News::select(self::NEWS_COLUMNS)
                ->with(['media' => fn ($q) => $this->applyMediaConstraints($q, $mimePrefix)])
                ->whereIn('id', $newsIds)
                ->get();

        $eventModels = $eventIds->isEmpty()
            ? collect()
            : Event::select(self::EVENT_COLUMNS)
                ->with(['media' => fn ($q) => $this->applyMediaConstraints($q, $mimePrefix)])
                ->whereIn('id', $eventIds)
                ->get();

        $ordered = $rows
            ->map(fn ($row) => $row->source_type === 'news'
                ? $newsModels->firstWhere('id', $row->id)
                : $eventModels->firstWhere('id', $row->id))
            ->filter()
            ->values();

        return new ConcretePaginator(
            items:       $ordered->all(),
            total:       $indexPage->total(),
            perPage:     $perPage,
            currentPage: $page,
            options:     [
                'path'     => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ],
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Aplica las restricciones de columnas y filtro de MIME a una query de media.
     * Se usa tanto en whereHas() como en with() para mantener consistencia.
     *
     * REGLA INVARIANTE: la galería NUNCA muestra documentos (PDF, Word, etc.),
     * sin importar el filtro de tipo seleccionado. Esta restricción se aplica
     * primero e incondicionalmente; el filtro de tipo ('image'/'video') sólo
     * acota más dentro de ese conjunto ya restringido a imágenes y videos.
     */
    private function applyMediaConstraints(Builder|Relation $query, ?string $mimePrefix): void
    {
        $query->select(self::MEDIA_COLUMNS)
              ->orderBy('order');

        // ── Exclusión incondicional de documentos ────────────────────────
        // Un enlace externo (path que empieza con "http", p. ej. YouTube)
        // siempre se trata como video. Cualquier mime que no sea image/* ni
        // video/* (PDF, Word, etc.) queda fuera sin importar el filtro.
        $query->where(fn (Builder $q) =>
            $q->where('mime', 'like', 'image/%')
              ->orWhere('mime', 'like', 'video/%')
              ->orWhere('path', 'like', 'http%')
        );

        // ── Filtro adicional de tipo de media, si se especificó uno ──────
        if ($mimePrefix === 'video/') {
            $query->where(fn (Builder $q) =>
                $q->where('mime', 'like', 'video/%')
                  ->orWhere('path', 'like', 'http%')
            );
        } elseif ($mimePrefix === 'image/') {
            // Para imágenes: solo archivos locales con MIME image/*
            $query->where('mime', 'like', 'image/%')
                  ->where('path', 'not like', 'http%');
        }
        // $mimePrefix === null ('all') → sin acotar más: ya quedó limitado
        // a imágenes y videos por la exclusión incondicional de arriba.
    }

    /**
     * Convierte el valor del filtro de tipo de la UI al prefijo MIME
     * correspondiente, validando contra una whitelist.
     *
     * @return string|null  Prefijo MIME o null si se muestran todos los tipos.
     */
    private function resolveMimePrefix(string $typeFilter): ?string
    {
        return match ($typeFilter) {
            'image' => 'image/',
            'video' => 'video/',
            default => null,   // 'all' u cualquier valor no reconocido → sin filtro
        };
    }

    /**
     * Limpia el término de búsqueda: elimina HTML y limita la longitud.
     * Aunque LIKE con bindings es seguro, strip_tags previene payloads en la salida.
     */
    private function sanitizeSearch(string $value): string
    {
        return mb_substr(strip_tags(trim($value)), 0, self::MAX_SEARCH_LENGTH);
    }
}
