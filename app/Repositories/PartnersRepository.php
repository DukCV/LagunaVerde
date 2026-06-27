<?php

namespace App\Repositories;

use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repositorio público de socios colaboradores (/colaboradores).
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD para el
 * listado público de Partner. A diferencia de AdminPartnersRepository,
 * esta clase SIEMPRE restringe a active=true y no expone operaciones
 * de escritura ni el filtro de estado de visibilidad.
 *
 * SEGURIDAD:
 *  - Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - Columna/dirección de orden validadas contra SORT_MAP → sin inyección de ORDER BY.
 *  - Filtro de tipo validado contra Partner::TYPES → sin bypass por valores arbitrarios.
 *
 * OPTIMIZACIÓN:
 *  - with() eager loading de 'media' (columnas mínimas) → previene N+1 en el grid.
 *  - select() explícito en paginate() → solo las columnas que el DTO necesita.
 *  - El índice idx_partners_active_created respalda el filtro y el orden por defecto.
 */
class PartnersRepository
{
    // ── Columnas mínimas necesarias para la tarjeta y el modal de detalles ──
    private const LIST_COLUMNS = [
        'id', 'name', 'type', 'who_they_are', 'how_they_support',
        'website', 'social_instagram', 'social_facebook', 'social_twitter', 'social_linkedin', 'social_youtube',
        'created_at', 'updated_at',
    ];

    // ── Lista blanca de columna/dirección de orden ───────────────────────
    // Previene inyección de ORDER BY con valores arbitrarios del cliente.
    private const SORT_MAP = [
        'recientes' => ['created_at', 'desc'],
        'antiguos'  => ['created_at', 'asc'],
        'nombre'    => ['name', 'asc'],
    ];

    private const MAX_SEARCH_LENGTH = 100;

    // ════════════════════════════════════════════════════════════════════
    //  CONSULTAS PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    public function paginate(string $search, string $type, string $sortBy, int $perPage = 9): LengthAwarePaginator
    {
        return $this->buildQuery($search, $type, $sortBy)
            ->paginate($perPage, self::LIST_COLUMNS);
    }

    /** Conteo de socios activos — alimenta el contador del banner. */
    public function countActive(): int
    {
        return Partner::active()->count();
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BUILDER
    // ════════════════════════════════════════════════════════════════════

    private function buildQuery(string $search, string $type, string $sortBy): Builder
    {
        $query = Partner::active()
            ->with([
                // Columnas mínimas necesarias para resolver el logo en el DTO
                'media:id,mediable_id,mediable_type,collection,path,disk,mime',
            ]);

        // ── Búsqueda por texto en nombre o descripciones ─────────────────
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('who_they_are', 'like', '%' . $term . '%')
                  ->orWhere('how_they_support', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro por categoría: validado contra Partner::TYPES ────────
        if ($type !== 'todos' && in_array($type, Partner::TYPES, strict: true)) {
            $query->where('type', $type);
        }

        // ── Ordenamiento contra lista blanca SORT_MAP ────────────────────
        [$column, $direction] = self::SORT_MAP[$sortBy] ?? self::SORT_MAP['recientes'];
        $query->orderBy($column, $direction);

        return $query;
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
