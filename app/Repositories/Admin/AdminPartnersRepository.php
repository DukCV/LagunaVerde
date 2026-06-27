<?php

namespace App\Repositories\Admin;

use App\Models\Media;
use App\Models\Partner;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repositorio de socios colaboradores para el panel de administración.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD relacionada
 * con la gestión administrativa de Partner (listado, mutaciones y archivos).
 * A diferencia del módulo de noticias, no se separa en un repositorio de
 * "formulario" aparte: el modelo Partner es deliberadamente simple (un solo
 * archivo adjunto, sin relaciones adicionales), por lo que dividir en dos
 * repositorios añadiría ceremonia sin beneficio real (ver principio YAGNI).
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - Columna y dirección de orden validadas contra lista blanca → sin inyección de ORDER BY.
 *  - Filtro de tipo validado contra Partner::TYPES → sin bypass por valores arbitrarios.
 *
 * OPTIMIZACIÓN:
 *  - with() eager loading de 'media' (columnas mínimas) → previene N+1 en el grid de tarjetas.
 *  - Índices idx_partners_active_created e idx_partners_type respaldan los filtros más comunes.
 */
class AdminPartnersRepository
{
    // ── Columnas mínimas necesarias para el listado administrativo ───────
    private const LIST_COLUMNS = [
        'id', 'name', 'type', 'active',
        'website', 'social_instagram', 'social_facebook',
        'social_twitter', 'social_linkedin', 'social_youtube',
        'who_they_are', 'how_they_support',
        'created_at', 'updated_at',
    ];

    // ── Lista blanca de columnas y dirección de ordenamiento ──────────────
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

    public function paginate(
        string $search,
        string $type,
        string $status,
        string $sortBy,
        int    $perPage = 12
    ): LengthAwarePaginator {
        return $this->buildQuery($search, $type, $status, $sortBy)
            ->paginate($perPage, self::LIST_COLUMNS);
    }

    /** Conteo de socios activos para la métrica del encabezado. */
    public function countActive(): int
    {
        return Partner::where('active', true)->count();
    }

    /** Carga un socio por su PK — usado por el servicio para los modales y el formulario. */
    public function find(int $id): Partner
    {
        return Partner::with('media:id,mediable_id,mediable_type,collection,path,disk,mime')
            ->findOrFail($id);
    }

    /**
     * Busca el perfil de socio vinculado a una cuenta de usuario (rol
     * 'Colaborador'). Usado por "Administrar rol" para precargar el
     * formulario si el usuario ya tiene un perfil creado.
     */
    public function findByUserId(int $userId): ?Partner
    {
        return Partner::with('media:id,mediable_id,mediable_type,collection,path,disk,mime')
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Vincula un socio con una cuenta de usuario — UPDATE directo por PK.
     * Idempotente: si ya estaba vinculado al mismo usuario, no cambia nada.
     */
    public function linkToUser(int $partnerId, int $userId): void
    {
        Partner::where('id', $partnerId)->update([
            'user_id'    => $userId,
            'updated_at' => now(),
        ]);
    }

    /**
     * Desvincula un socio de su cuenta de usuario — UPDATE directo por PK.
     * El registro del socio permanece intacto; solo deja de pertenecer a
     * una cuenta de usuario (vuelve a ser un socio público tradicional).
     */
    public function unlinkUser(int $partnerId): void
    {
        Partner::where('id', $partnerId)->update([
            'user_id'    => null,
            'updated_at' => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  SELECTOR DE "COLABORADORES INVITADOS" — EventForm
    // ════════════════════════════════════════════════════════════════════

    /**
     * Búsqueda ligera de socios activos para el selector de "Colaboradores
     * invitados" del formulario de eventos. Sin paginación: la grilla
     * siempre muestra como máximo $limit resultados; el admin refina con
     * texto/tipo si necesita otro — evita un componente de paginación
     * adicional dentro de un formulario ya extenso.
     *
     * Solo socios activos: invitar a un socio oculto del sitio público a un
     * evento (también público) sería inconsistente.
     *
     * @param int[] $excludeIds Socios ya añadidos al evento — se excluyen
     *   para no ofrecer un botón "Agregar" duplicado en la grilla.
     * @return Collection<int, Partner>
     */
    public function searchActiveForPicker(string $search, string $type, array $excludeIds, int $limit = 12): Collection
    {
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);

        $query = Partner::active()
            ->with(['media:id,mediable_id,mediable_type,collection,path,disk,mime']);

        if ($term !== '') {
            $query->where('name', 'like', '%' . $term . '%');
        }

        if ($type !== 'todos' && in_array($type, Partner::TYPES, strict: true)) {
            $query->where('type', $type);
        }

        if ($excludeIds !== []) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('name')->limit($limit)->get(['id', 'name', 'type']);
    }

    /** Un solo socio activo, para revalidar+resolver un ID antes de añadirlo a un evento. */
    public function findActiveForPicker(int $id): ?Partner
    {
        return Partner::active()
            ->with(['media:id,mediable_id,mediable_type,collection,path,disk,mime'])
            ->find($id, ['id', 'name', 'type']);
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BUILDER
    // ════════════════════════════════════════════════════════════════════

    private function buildQuery(
        string $search,
        string $type,
        string $status,
        string $sortBy,
    ): Builder {
        $query = Partner::with([
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

        // ── Filtro por tipo: validado contra Partner::TYPES ──────────────
        if ($type !== 'todos' && in_array($type, Partner::TYPES, strict: true)) {
            $query->where('type', $type);
        }

        // ── Filtro por estado de visibilidad ─────────────────────────────
        if ($status === 'activos') {
            $query->where('active', true);
        } elseif ($status === 'inactivos') {
            $query->where('active', false);
        }

        // ── Ordenamiento contra lista blanca SORT_MAP ────────────────────
        [$column, $direction] = self::SORT_MAP[$sortBy] ?? self::SORT_MAP['recientes'];
        $query->orderBy($column, $direction);

        return $query;
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — SOCIO
    // ════════════════════════════════════════════════════════════════════

    public function create(array $attrs): Partner
    {
        return Partner::create($attrs);
    }

    public function update(int $id, array $attrs): void
    {
        Partner::where('id', $id)->update($attrs + ['updated_at' => now()]);
    }

    /**
     * Alterna la visibilidad pública de un socio.
     * UPDATE directo por PK — sin instanciar el modelo ni cargar relaciones.
     */
    public function toggleActive(int $id, bool $newStatus): void
    {
        Partner::where('id', $id)->update([
            'active'     => $newStatus,
            'updated_at' => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — LOGOTIPO (colección 'logo' en la tabla media)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve las rutas físicas del logo actual del socio (a lo sumo un registro).
     *
     * @return array<int, array{disk: string, path: string}>
     */
    public function logoPaths(int $partnerId): array
    {
        return Media::where('mediable_type', (new Partner)->getMorphClass())
            ->where('mediable_id', $partnerId)
            ->where('collection', 'logo')
            ->get(['disk', 'path'])
            ->map(fn (Media $media) => ['disk' => $media->disk, 'path' => $media->path])
            ->all();
    }

    /** Elimina el registro de media del logo actual (los archivos físicos se borran aparte). */
    public function deleteLogo(int $partnerId): void
    {
        Media::where('mediable_type', (new Partner)->getMorphClass())
            ->where('mediable_id', $partnerId)
            ->where('collection', 'logo')
            ->delete();
    }

    // ════════════════════════════════════════════════════════════════════
    //  ELIMINACIÓN PERMANENTE EN CASCADA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina en cascada un socio y su logotipo asociado.
     * Debe invocarse dentro de una transacción (ver AdminPartnersService::eliminarSocio).
     *
     * @return array<int, array{disk: string, path: string}> Rutas físicas a borrar tras el commit
     */
    public function deleteWithCascade(int $id): array
    {
        $archivos = $this->logoPaths($id);

        $this->deleteLogo($id);

        Partner::where('id', $id)->delete();

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
