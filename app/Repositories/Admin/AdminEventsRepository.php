<?php

namespace App\Repositories\Admin;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Event;
use App\Models\EventCollaborator;
use App\Models\Media;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repositorio de eventos para el panel de administración.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD relacionada
 * con la gestión administrativa de Event. Expone TODOS los estados
 * (draft, published, cancelled, closed) — a diferencia de cualquier vista
 * pública que solo muestre eventos publicados.
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - select() explícito en la paginación → 'content' (longText) nunca viaja al listado.
 *  - Inputs sanitizados antes de usarse en cláusulas WHERE.
 *  - Filtro de estado y columna/dirección de ordenamiento validados contra listas blancas.
 *
 * OPTIMIZACIÓN:
 *  - with() eager loading para 'media' y 'category' → previene N+1 en el grid de tarjetas.
 *  - withCount() agrega el conteo de inscripciones activas en la query principal.
 *  - select() explícito con columnas mínimas necesarias → menor transferencia de datos.
 */
class AdminEventsRepository
{
    // ── Columnas mínimas para el listado administrativo ──────────────────
    // 'content' (longText) está deliberadamente ausente — solo se usaría en una vista de detalle.
    private const LIST_COLUMNS = [
        'id',
        'uuid',
        'name',
        'description',
        'location',
        'category_id',
        'start_at',
        'end_at',
        'capacity_total',
        'status',
        'created_at',
        'updated_at',
    ];

    // Estados con los que un evento puede cancelarse (transición de un solo sentido)
    private const CANCELABLE_STATUSES = ['draft', 'published'];

    // ── Lista blanca de columnas y dirección de ordenamiento ─────────────
    // Previene inyección de ORDER BY con valores arbitrarios del cliente.
    // 'registrations_count' es el alias generado por withCount() en buildQuery()
    // — un alias de SELECT, no una columna de la tabla, pero válido como
    // destino de ORDER BY igual que cualquier columna (mismo patrón que EventsWidget).
    private const SORT_MAP = [
        'recientes'       => ['created_at',          'desc'],
        'proximos'        => ['start_at',             'asc'],
        'lejanos'         => ['start_at',             'desc'],
        'mas-inscritos'   => ['registrations_count',  'desc'],
        'menos-inscritos' => ['registrations_count',  'asc'],
    ];

    private const MAX_SEARCH_LENGTH   = 100;
    private const MAX_CATEGORY_LENGTH = 120;

    // ════════════════════════════════════════════════════════════════════
    //  CONSULTAS PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginación de eventos con todos los filtros administrativos aplicados.
     *
     * Incluye eager loading de media, category y conteo de inscripciones activas
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
     * Conteo de eventos agrupados por estado, en una sola consulta SQL.
     * Garantiza las 4 claves del enum aunque algún estado no tenga registros.
     *
     * @return array<string, int>
     */
    public function countsByStatus(): array
    {
        $counts = Event::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return array_merge(
            array_fill_keys(Event::STATUSES, 0),
            $counts->toArray()
        );
    }

    /**
     * Lista de categorías tipo 'events' que tienen al menos un evento asignado.
     * Usada para construir el selector de filtro de categoría.
     *
     * @return Collection<int, string>
     */
    public function availableCategories(): Collection
    {
        return Category::forEvents()
            ->whereHas('events')
            ->orderBy('name')
            ->pluck('name');
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BUILDER
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye la query base con eager loading, filtros aplicados
     * y ordenamiento según la lista blanca SORT_MAP.
     */
    private function buildQuery(
        string $search,
        string $status,
        string $category,
        string $sortBy,
    ): Builder {
        $query = Event::query()
            ->with([
                // Solo id y nombre de categoría para el badge
                'category:id,name',
                // Solo la portada (order = 0): se filtra en PHP vía DTO, pero limitar
                // aquí reduce el volumen transferido cuando hay muchos adjuntos por evento
                'media' => fn ($q) => $q->where('order', 0)->limit(1),
            ])
            // Inscripciones activas (registered + waitlist) en la misma query — sin N+1
            ->withCount([
                'registrations as registrations_count' => fn ($q) => $q->whereIn(
                    'status',
                    ['registered', 'waitlist']
                ),
            ]);

        // ── Búsqueda por texto en nombre, descripción o ubicación ────────
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('description', 'like', '%' . $term . '%')
                  ->orWhere('location', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro por estado: validado contra Event::STATUSES ───────────
        if ($status !== 'todos' && in_array($status, Event::STATUSES, strict: true)) {
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
        [$column, $direction] = self::SORT_MAP[$sortBy] ?? self::SORT_MAP['proximos'];
        $query->orderBy($column, $direction);

        return $query;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MUTACIONES DE ESTADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Cancela un evento — transición de un solo sentido (sin "reactivar").
     *
     * RENDIMIENTO: UPDATE directo por PK, sin instanciar el modelo.
     * SEGURIDAD: whereIn(CANCELABLE_STATUSES) evita cancelar un evento que ya
     * está cancelado o finalizado (transición inválida → 0 filas afectadas).
     */
    public function cancel(int $id): void
    {
        Event::query()
            ->where('id', $id)
            ->whereIn('status', self::CANCELABLE_STATUSES)
            ->update([
                'status'     => 'cancelled',
                'updated_at' => now(),
            ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ELIMINACIÓN PERMANENTE EN CASCADA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina en cascada un evento y sus registros relacionados.
     *
     * Debe invocarse dentro de una transacción de BD (ver AdminEventsService::eliminarEvento).
     *
     * NOTA: 'event_registrations' y 'event_partner' tienen cascadeOnDelete()
     * a nivel de FK en su migración, por lo que el DELETE del evento ya
     * elimina sus inscripciones y filas de colaboradores automáticamente —
     * no se gestionan aquí para evitar trabajo redundante. 'comments' y
     * 'media' son relaciones polimórficas sin FK, así que sí requieren
     * borrado explícito (mismo patrón que AdminNewsRepository).
     *
     * Los logotipos de colaboradores EXTERNOS ('event_partner.custom_logo_path')
     * sí deben recopilarse aquí de forma explícita pese al cascade de la
     * fila: a diferencia de 'media', esa ruta no vive en la tabla
     * polimórfica 'media', así que el cascade del FK borraría la fila en BD
     * pero dejaría el archivo físico huérfano en disco si no se recolecta
     * ANTES del DELETE del evento.
     *
     * @return array<int, array{disk: string, path: string}> Rutas físicas a borrar tras el commit
     */
    public function deleteEventWithCascade(int $id): array
    {
        // El morph map registra 'event' como alias corto de App\Models\Event
        $morphClass = (new Event)->getMorphClass();

        // ── 1. Recopilar rutas físicas de TODOS los adjuntos antes de borrarlos ──
        $archivos = Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $id)
            ->get(['disk', 'path'])
            ->map(fn (Media $media) => ['disk' => $media->disk, 'path' => $media->path])
            ->all();

        // ── 1b. Recopilar logotipos de colaboradores externos (ver nota arriba) ──
        $logosColaboradores = EventCollaborator::where('event_id', $id)
            ->whereNotNull('custom_logo_path')
            ->pluck('custom_logo_path')
            ->map(fn (string $ruta) => ['disk' => 'public', 'path' => $ruta])
            ->all();

        $archivos = array_merge($archivos, $logosColaboradores);

        // ── 2. Eliminar comentarios en una sola sentencia DELETE masiva ──────
        Comment::where('commentable_type', $morphClass)
            ->where('commentable_id', $id)
            ->delete();

        // ── 3. Eliminar registros de media (los archivos físicos se borran
        //      después del commit, ver AdminEventsService::eliminarEvento) ───
        Media::where('mediable_type', $morphClass)
            ->where('mediable_id', $id)
            ->delete();

        // ── 4. Eliminar el evento — inscripciones Y colaboradores se
        //      eliminan en cascada automáticamente vía FK
        //      (event_registrations.event_id y event_partner.event_id) ──────
        Event::where('id', $id)->delete();

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
