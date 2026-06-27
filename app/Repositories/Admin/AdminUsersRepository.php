<?php

namespace App\Repositories\Admin;

use App\Models\Role;
use App\Models\RoleUser;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Repositorio de usuarios para el panel de administración.
 *
 * RESPONSABILIDAD ÚNICA: encapsular toda interacción con la BD relacionada
 * con el listado administrativo de App\Models\User.
 *
 * ALCANCE DE ESTA ITERACIÓN:
 *  "Borrar definitivamente" sigue sin lógica de mutación (deshabilitado a
 *  propósito en la vista). "Activar/Inhabilitar" — ver toggleActive() — y
 *  "Administrar rol" — ver findRoleIdByName()/syncRole() — SÍ son
 *  funcionales. Todos son UPDATE directos por PK, sin necesitar transacción
 *  por sí solos (la transacción del cambio de rol, cuando incluye un perfil
 *  de colaborador, vive en AdminRoleService::guardarCambiosDeRol()).
 *
 * SEGURIDAD:
 *  - Toda condición usa Eloquent Builder → PDO prepared statements, sin SQL Injection.
 *  - select() explícito en la paginación → 'password' y 'remember_token' nunca
 *    viajan al listado (ya están en $hidden, esto es una defensa adicional de rendimiento).
 *  - Filtro de categoría y columna/dirección de ordenamiento validados contra listas blancas.
 *
 * OPTIMIZACIÓN:
 *  - with('roles:id,name') eager loading → previene N+1 al mostrar el rol de cada fila.
 *  - countsByCategory() resuelve las 5 categorías en solo 3 consultas agregadas con índices.
 */
class AdminUsersRepository
{
    // ── Nombres de rol — únicos en todo el sistema (ver Database\Seeders\UserSeeder) ──
    public const ROL_ADMINISTRADOR  = 'Administrador';
    public const ROL_USUARIO_NORMAL = 'Usuario Normal';
    public const ROL_COLABORADOR    = 'Colaborador';

    // ── Columnas mínimas para el listado administrativo ──────────────────
    private const LIST_COLUMNS = [
        'id',
        'uuid',
        'name',
        'email',
        'phone',
        'profile_photo_path',
        'active',
        'created_at',
    ];

    // ── Categorías válidas del filtro (lista blanca) ─────────────────────
    private const CATEGORIAS_VALIDAS = ['todos', 'normal', 'colaborador', 'administrador', 'inactivos'];

    // ── Lista blanca de columna/dirección de ordenamiento ─────────────────
    // Previene inyección de ORDER BY con valores arbitrarios del cliente.
    private const SORT_MAP = [
        'recientes' => ['created_at', 'desc'],
        'antiguos'  => ['created_at', 'asc'],
        'nombre-az' => ['name',       'asc'],
        'nombre-za' => ['name',       'desc'],
    ];

    private const MAX_SEARCH_LENGTH = 100;

    // ── Límites de "registros por página" — defensa final, independiente de
    //    la lista blanca de la UI (ver AdminUsersService::PER_PAGE_OPTIONS).
    //    Protege contra un valor manipulado (ej. 999999) que agote memoria o
    //    CPU en el hosting compartido, sin importar quién llame a paginate().
    private const MIN_PER_PAGE = 5;
    private const MAX_PER_PAGE = 100;

    // ── Columnas mínimas para el modal de activar/inhabilitar ────────────
    private const STATUS_COLUMNS = ['id', 'name', 'active'];

    // ── Columnas mínimas para el selector de "vincular usuario" del
    //    formulario de socios colaboradores ──────────────────────────────
    private const PICKER_COLUMNS = ['id', 'name', 'email', 'profile_photo_path'];
    private const PICKER_LIMIT    = 8;

    // ════════════════════════════════════════════════════════════════════
    //  CONSULTAS PRINCIPALES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginación de usuarios con todos los filtros administrativos aplicados.
     * Incluye eager loading de roles para evitar el problema N+1 en la tabla.
     *
     * RENDIMIENTO: paginate() de Eloquent ejecuta una consulta COUNT(*) y una
     * consulta SELECT ... LIMIT/OFFSET — la BD nunca devuelve más filas que
     * $perPage; la colección completa de usuarios nunca se carga en memoria.
     */
    public function paginate(
        string $search,
        string $categoria,
        string $sortBy,
        int    $perPage = 25
    ): LengthAwarePaginator {
        return $this->buildQuery($search, $categoria, $sortBy)
            ->paginate($this->clampPerPage($perPage), self::LIST_COLUMNS);
    }

    /**
     * Conteo de usuarios por categoría en solo 3 consultas agregadas:
     *  1) total real de usuarios (para el encabezado),
     *  2) usuarios activos agrupados por rol (una sola consulta con JOIN),
     *  3) usuarios inactivos (cualquier rol).
     *
     * NOTA: las categorías de rol cuentan solo cuentas activas, de modo que
     * "Administradores", "Colaboradores" y "Usuarios normales" no se solapan
     * con "Inactivos" — son particiones mutuamente excluyentes del total.
     *
     * @return array<string, int>
     */
    public function countsByCategory(): array
    {
        $porRol = User::query()
            ->where('active', true)
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->selectRaw('roles.name as rol, count(*) as total')
            ->groupBy('roles.name')
            ->pluck('total', 'rol');

        return [
            'todos'         => User::query()->count(),
            'normal'        => (int) ($porRol[self::ROL_USUARIO_NORMAL] ?? 0),
            'colaborador'   => (int) ($porRol[self::ROL_COLABORADOR] ?? 0),
            'administrador' => (int) ($porRol[self::ROL_ADMINISTRADOR] ?? 0),
            'inactivos'     => User::query()->where('active', false)->count(),
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE ACTIVAR / INHABILITAR
    // ════════════════════════════════════════════════════════════════════

    /** Carga un usuario por su PK — usado por el servicio para el modal de cambio de estado. */
    public function find(int $id): User
    {
        return User::select(self::STATUS_COLUMNS)->findOrFail($id);
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     * UPDATE directo por PK — sin instanciar el modelo ni cargar relaciones.
     */
    public function toggleActive(int $id, bool $newStatus): void
    {
        User::where('id', $id)->update([
            'active'     => $newStatus,
            'updated_at' => now(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL "ADMINISTRAR ROL"
    // ════════════════════════════════════════════════════════════════════

    /**
     * Carga un usuario con sus roles (y el pivote position/permissions/
     * public_bio/social_links/show_in_about_us de cada uno).
     * Usado para precargar el modal "Administrar rol" y la página "Mi
     * Perfil" (ver AdminRoleService::getRoleManagementData()/getMyProfileData()).
     */
    public function findWithRoles(int $id): User
    {
        return User::with('roles:id,name')->findOrFail($id);
    }

    /**
     * Activa/desactiva si el perfil de un usuario (en una asignación de rol
     * concreta) se muestra en "Quiénes Somos" → "Nuestro Equipo".
     * UPDATE directo por las claves compuestas de role_user — sin pasar por
     * sync()/fill(), que reemplazaría la fila pivote completa cuando aquí
     * solo se necesita cambiar una columna.
     */
    public function setShowInAboutUs(int $userId, int $roleId, bool $valor): void
    {
        RoleUser::where('user_id', $userId)
            ->where('role_id', $roleId)
            ->update(['show_in_about_us' => $valor]);
    }

    /**
     * Resuelve el ID de un rol por su nombre.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el rol no existe
     */
    public function findRoleIdByName(string $roleName): int
    {
        return Role::where('name', $roleName)->firstOrFail()->id;
    }

    /**
     * Reemplaza la asignación de rol del usuario por una sola — este panel
     * gestiona exactamente un rol a la vez, igual que el resto del sistema
     * (ver UserSeeder) — y guarda 'position'/'permissions' en el pivote de
     * esa asignación.
     *
     * 'permissions' se pasa como array PHP plano, NUNCA pre-codificado a
     * JSON: al haber un pivote con clase propia (App\Models\RoleUser, vía
     * ->using() en User::roles()), sync() actualiza/crea la fila pivote a
     * través de $pivot->fill($attributes)->save() — es decir, SÍ pasa por
     * el cast 'array' del modelo, que ya hace el json_encode() al guardar.
     * Codificarlo aquí también produciría doble codificación.
     */
    public function syncRole(int $userId, int $roleId, ?string $position, array $permissions): void
    {
        $user = User::findOrFail($userId);

        $user->roles()->sync([
            $roleId => [
                'position'    => $position,
                'permissions' => $permissions !== [] ? $permissions : null,
            ],
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  SELECTOR "VINCULAR USUARIO" — formulario de socios colaboradores
    // ════════════════════════════════════════════════════════════════════

    /**
     * Búsqueda ligera de usuarios para vincular a un socio colaborador
     * (mismo patrón que AdminPartnersRepository::searchActiveForPicker,
     * usado por EventForm). Sin paginación: como máximo $limit resultados —
     * evita un componente de paginación adicional dentro de un formulario
     * ya extenso.
     *
     * SOLO usuarios con rol 'Usuario Normal':
     *  - Administradores y Colaboradores quedan fuera a propósito. Vincular
     *    este formulario automáticamente otorga el rol 'Colaborador' al
     *    guardar (ver AdminPartnersService::sincronizarUsuarioVinculado()) —
     *    permitir elegir a un Administrador lo "degradaría" sin que esa sea
     *    la intención del formulario, y un Colaborador ya tiene su propio
     *    perfil vinculado en otra parte.
     *
     * Adicionalmente excluye (defensa en profundidad ante datos inconsistentes)
     * a cualquier usuario que YA tenga un perfil de socio vinculado a OTRO
     * partner — partners.user_id es UNIQUE, así que ofrecerlo sería un
     * callejón sin salida en el formulario. Sí se incluye el usuario
     * vinculado al socio que se está editando ($currentPartnerId).
     *
     * RENDIMIENTO: select() mínimo + límite fijo — la BD nunca devuelve más
     * de $limit filas ni columnas innecesarias (password/remember_token
     * nunca se seleccionan).
     *
     * @return Collection<int, User>
     */
    public function searchForPartnerLinking(string $search, ?int $currentPartnerId, int $limit = self::PICKER_LIMIT): Collection
    {
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);

        $query = User::query()
            ->select(self::PICKER_COLUMNS)
            ->whereHas('roles', fn (Builder $q) => $q->where('name', self::ROL_USUARIO_NORMAL))
            ->where(function (Builder $q) use ($currentPartnerId) {
                $q->whereDoesntHave('partner');

                if ($currentPartnerId !== null) {
                    $q->orWhereHas('partner', fn (Builder $sub) => $sub->where('id', $currentPartnerId));
                }
            });

        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('email', 'like', '%' . $term . '%')
                  ->orWhere('phone', 'like', '%' . $term . '%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get();
    }

    /** Un solo usuario por su PK, con columnas mínimas — para la tarjeta de "usuario seleccionado". */
    public function findBasicInfo(int $id): ?User
    {
        return User::select(self::PICKER_COLUMNS)->find($id);
    }

    // ════════════════════════════════════════════════════════════════════
    //  QUERY BUILDER
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye la query base con eager loading, filtros aplicados
     * y ordenamiento según la lista blanca SORT_MAP.
     */
    private function buildQuery(string $search, string $categoria, string $sortBy): Builder
    {
        $query = User::query()->with(['roles:id,name']);

        // ── Búsqueda por texto en nombre, correo o teléfono ──────────────
        $term = $this->sanitize($search, self::MAX_SEARCH_LENGTH);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', '%' . $term . '%')
                  ->orWhere('email', 'like', '%' . $term . '%')
                  ->orWhere('phone', 'like', '%' . $term . '%');
            });
        }

        // ── Filtro por categoría: whitelist contra CATEGORIAS_VALIDAS ────
        if (in_array($categoria, self::CATEGORIAS_VALIDAS, strict: true)) {
            match ($categoria) {
                'normal' => $query->where('active', true)->whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', self::ROL_USUARIO_NORMAL)
                ),
                'colaborador' => $query->where('active', true)->whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', self::ROL_COLABORADOR)
                ),
                'administrador' => $query->where('active', true)->whereHas(
                    'roles',
                    fn (Builder $q) => $q->where('name', self::ROL_ADMINISTRADOR)
                ),
                'inactivos' => $query->where('active', false),
                default     => null, // 'todos': sin filtro adicional
            };
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

    /**
     * Acota "registros por página" a un rango seguro sin importar el valor
     * recibido — última línea de defensa antes de que llegue a la consulta SQL.
     */
    private function clampPerPage(int $perPage): int
    {
        return max(self::MIN_PER_PAGE, min($perPage, self::MAX_PER_PAGE));
    }
}
