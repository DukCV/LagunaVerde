<?php

namespace App\Services\Admin;

use App\DTOs\Admin\AdminUserItemDto;
use App\Repositories\Admin\AdminUsersRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Servicio de gestión de usuarios para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas al AdminUsersRepository.
 *  - Transformar colecciones de modelos Eloquent en DTOs listos para la vista.
 *  - Construir los arrays de opciones para los selectores/tabs de filtro.
 *  - Ser la única dependencia que el componente Livewire necesita inyectar.
 *
 * El componente Livewire no conoce ni el modelo User ni el repositorio;
 * solo trabaja con DTOs y arrays de opciones primitivos.
 *
 * ALCANCE DE ESTA ITERACIÓN:
 *  "Borrar definitivamente" sigue siendo un marcador visual inactivo a
 *  propósito (ver users-management.blade.php). "Activar/Inhabilitar" — ver
 *  toggleUserStatus() — es funcional. "Administrar rol" (cambio de rol,
 *  permisos y perfil de colaborador) vive en App\Services\Admin\AdminRoleService,
 *  un servicio dedicado — no en este, para mantener responsabilidades separadas.
 */
class AdminUsersService
{
    // Opciones del selector de orden — lista estática, no depende de la BD.
    // Las claves coinciden exactamente con AdminUsersRepository::SORT_MAP.
    private const SORT_OPTIONS = [
        'recientes' => 'Más recientes primero',
        'antiguos'  => 'Más antiguos primero',
        'nombre-az' => 'Nombre A-Z',
        'nombre-za' => 'Nombre Z-A',
    ];

    // Opciones/etiquetas de las tabs de categoría — coinciden con
    // AdminUsersRepository::CATEGORIAS_VALIDAS.
    private const CATEGORY_OPTIONS = [
        'todos'         => 'Todos',
        'normal'        => 'Usuarios normales',
        'colaborador'   => 'Colaboradores',
        'administrador' => 'Administradores',
        'inactivos'     => 'Inactivos',
    ];

    // Opciones del selector "registros por página" — única fuente de verdad
    // usada tanto para renderizar el <select> como para validar el valor
    // entrante en UsersManagement::updatedPerPage() (lista blanca de la UI).
    // El límite duro independiente vive en AdminUsersRepository::MAX_PER_PAGE.
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const DEFAULT_PER_PAGE = 25;

    public function __construct(
        private readonly AdminUsersRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por UsersManagement
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador de usuarios con cada ítem transformado a AdminUserItemDto.
     *
     * Usa through() para transformación lazy sobre el paginador —
     * no carga toda la colección en memoria antes de transformar.
     *
     * @return LengthAwarePaginator<AdminUserItemDto>
     */
    public function getPaginatedUsers(
        string $search,
        string $categoria,
        string $sortBy,
        int    $perPage = self::DEFAULT_PER_PAGE
    ): LengthAwarePaginator {
        $paginator = $this->repository->paginate($search, $categoria, $sortBy, $perPage);

        return $paginator->through(
            fn ($user) => AdminUserItemDto::fromModel($user)
        );
    }

    /**
     * Conteo de usuarios por categoría para las métricas y tabs del encabezado.
     *
     * @return array<string, int>
     */
    public function getCountsByCategory(): array
    {
        return $this->repository->countsByCategory();
    }

    /**
     * Opciones de las tabs de categoría [valor => etiqueta].
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        return self::CATEGORY_OPTIONS;
    }

    /**
     * Opciones del selector de orden [valor => etiqueta].
     *
     * @return array<string, string>
     */
    public function getSortOptions(): array
    {
        return self::SORT_OPTIONS;
    }

    /**
     * Opciones válidas del selector "registros por página" — lista blanca
     * usada tanto por la vista (para renderizar el <select>) como por el
     * componente Livewire (para validar el valor entrante).
     *
     * @return array<int, int>
     */
    public function getPerPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    /** Valor de "registros por página" usado cuando el recibido no es válido. */
    public function getDefaultPerPage(): int
    {
        return self::DEFAULT_PER_PAGE;
    }

    // ════════════════════════════════════════════════════════════════════
    //  MODAL DE ACTIVAR / INHABILITAR
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve el estado activo/inactivo actual de un usuario directamente
     * desde la BD. Usado por el componente para decidir si el modal debe
     * mostrar "Inhabilitar" o "Activar".
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getUserStatus(int $id): bool
    {
        return $this->repository->find($id)->active;
    }

    /**
     * Devuelve el nombre de un usuario directamente desde la BD.
     * Usado para construir el texto dinámico del modal de confirmación.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getUserName(int $id): string
    {
        return $this->repository->find($id)->name;
    }

    /**
     * Alterna el estado activo/inactivo de un usuario.
     * El nuevo estado se determina leyendo el estado ACTUAL desde la BD —
     * nunca se confía en el estado enviado por el cliente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function toggleUserStatus(int $id): void
    {
        $activo = $this->repository->find($id)->active;

        $this->repository->toggleActive($id, ! $activo);
    }
}
