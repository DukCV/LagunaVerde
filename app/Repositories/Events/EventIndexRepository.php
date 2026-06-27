<?php

namespace App\Repositories\Events;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repositorio para el listado y banner de eventos.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD
 * relacionada con el índice de eventos. Los filtros se aplican
 * en la query SQL — nunca en PHP sobre arrays cargados en memoria.
 *
 * SEGURIDAD:
 *  - Toda condición WHERE usa Eloquent → PDO prepared statements.
 *  - select() explícito — 'content' (HTML largo) excluido siempre.
 *  - Categoría validada por whiteList en el servicio antes de llegar aquí.
 *  - limit() siempre acotado → sin dumps masivos.
 *  - Búsqueda LIKE usa bindings internos de Eloquent → sin SQL Injection.
 */
class EventIndexRepository
{
    // Columnas mínimas para el listado — 'content' deliberadamente excluido
    private const LIST_COLUMNS = [
        'id',
        'uuid',
        'name',
        'description',
        'category_id',
        'start_at',
        'end_at',        // nullable — hora de término opcional
        'capacity_total',
        'status',
    ];

    // Longitudes máximas de input de búsqueda
    private const MAX_SEARCH_LEN   = 100;
    private const MAX_CATEGORY_LEN = 120;

    // ════════════════════════════════════════════════════════════════════
    //  EVENTO DESTACADO — el más próximo publicado
    // ════════════════════════════════════════════════════════════════════

    /**
     * Retorna el evento publicado más próximo (start_at > now) o null.
     * Se usa en el banner principal de la página de eventos.
     */
    public function findFeatured(): ?Event
    {
        return Event::select(self::LIST_COLUMNS)
            ->where('status', 'published')
            ->where('start_at', '>', now())
            ->with($this->eagerLoads())
            ->orderBy('start_at')
            ->first();
    }

    // ════════════════════════════════════════════════════════════════════
    //  LISTADO PAGINADO CON FILTROS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Listado paginado de eventos con filtros combinables aplicados en BD.
     *
     * ORDEN:
     *  1. Eventos activos (published + futuros), del más próximo al más lejano.
     *  2. Eventos pasados/cancelados al final, del más reciente al más antiguo.
     *
     * El ordenamiento compuesto se logra con dos columnas ORDER BY:
     *   ORDER BY (start_at > NOW()) DESC, start_at ASC / DESC
     *
     * @param  string $search       Término libre (título o descripción).
     * @param  string $categoryName Nombre de categoría o '' para todas.
     * @param  string $monthYear    "YYYY-MM" o '' para todos los meses.
     * @param  string $status       'active' | 'finished' | 'all'
     * @param  int    $perPage      Registros por página.
     */
    public function paginate(
        string $search,
        string $categoryName,
        string $monthYear,
        string $status,
        int    $perPage = 9
    ): LengthAwarePaginator {
        $query = Event::select(self::LIST_COLUMNS)
            ->with($this->eagerLoads());

        // ── Filtro: texto libre ──────────────────────────────────────────
        $term = $this->sanitize($search, self::MAX_SEARCH_LEN);
        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('name',        'like', '%' . $term . '%')
                  ->orWhere('description', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro: categoría (validada externamente por whitelist) ──────
        $cat = $this->sanitize($categoryName, self::MAX_CATEGORY_LEN);
        if ($cat !== '') {
            $query->whereHas('category', fn ($q) => $q->where('name', $cat));
        }

        // ── Filtro: mes y año ("YYYY-MM") ────────────────────────────────
        if ($monthYear !== '' && preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            [$year, $month] = explode('-', $monthYear);
            $query->whereYear('start_at', (int) $year)
                  ->whereMonth('start_at', (int) $month);
        }

        // ── Filtro: estado activo / finalizado / todos ───────────────────
        match($status) {
            'active'   => $query->where('status', 'published')
                                ->where('start_at', '>', now()),
            'finished' => $query->where(fn ($q) =>
                              $q->where('start_at', '<=', now())
                                ->orWhere('status', 'cancelled')
                          ),
            default    => null,   // 'all' — sin filtro adicional de estado
        };

        // ── Orden compuesto ──────────────────────────────────────────────
        // Activos primero (start_at futuro = 1), pasados al final (= 0).
        // Dentro de cada grupo: activos ASC (más próximos), pasados DESC.
        $query->orderByRaw('(start_at > NOW()) DESC')
              ->orderByRaw('CASE WHEN start_at > NOW() THEN start_at END ASC')
              ->orderByRaw('CASE WHEN start_at <= NOW() THEN start_at END DESC');

        return $query->paginate($perPage, ['*'], 'page');
    }

    // ════════════════════════════════════════════════════════════════════
    //  OPCIONES DE FILTROS — leídas de BD
    // ════════════════════════════════════════════════════════════════════

    /**
     * Categorías disponibles con al menos un evento publicado.
     * Se usa para construir el select de categorías con valores reales.
     *
     * @return string[]
     */
    public function availableCategories(): array
    {
        return \App\Models\Category::where('type', 'events')
            ->whereHas('events', fn ($q) => $q->where('status', 'published'))
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Meses/años disponibles con al menos un evento publicado.
     * Retorna array de ['value' => 'YYYY-MM', 'label' => 'Mes YYYY'].
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function availableMonths(): array
    {
        return Event::where('status', 'published')
            ->selectRaw("DATE_FORMAT(start_at, '%Y-%m') as month_key,
                         DATE_FORMAT(start_at, '%M %Y')  as month_label")
            ->groupBy('month_key', 'month_label')
            ->orderBy('month_key')
            ->get()
            ->map(fn ($row) => [
                'value' => $row->month_key,
                'label' => ucfirst($row->month_label),
            ])
            ->toArray();
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Eager loads compartidos entre findFeatured() y paginate().
     * Centralizado para garantizar consistencia y evitar duplicación.
     */
    private function eagerLoads(): array
    {
        return [
            // Solo portada — una imagen por evento
            'media' => fn ($q) => $q
                ->select(['id', 'mediable_id', 'mediable_type',
                          'path', 'disk', 'mime', 'alt', 'title', 'order'])
                ->where('order', 0)
                ->where('mime', 'like', 'image/%'),

            // Solo nombre de categoría
            'category:id,name',

            // Solo estado de registros — sin datos personales
            'registrations' => fn ($q) => $q
                ->select(['id', 'event_id', 'status'])
                ->whereIn('status', ['registered', 'waitlist']),
        ];
    }

    /** Sanitiza input: elimina HTML y limita longitud. */
    private function sanitize(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
