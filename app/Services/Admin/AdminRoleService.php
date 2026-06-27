<?php

namespace App\Services\Admin;

use App\Repositories\Admin\AdminUsersRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;

/**
 * Servicio de gestión de rol, permisos y perfil de colaborador para el panel
 * de administración ("Administrar rol" en Gestión de Usuarios).
 *
 * RESPONSABILIDADES:
 *  - Ser la única fuente de verdad del catálogo de permisos granulares y de
 *    la correspondencia entre las claves de rol de la UI ('usuario',
 *    'colaborador', 'administrador') y los nombres de rol reales en BD.
 *  - Orquestar el guardado atómico de: (a) la asignación de rol con su
 *    pivote 'position'/'permissions', y (b) cuando corresponde, el perfil
 *    de socio colaborador vinculado (creación/actualización + vínculo).
 *  - Construir los datos de precarga del modal desde la BD — nunca se
 *    confía en lo que el cliente afirme sobre el rol/permisos actuales.
 *
 * ALCANCE: este servicio permite ALMACENAR los permisos granulares. La
 * aplicación real de esos permisos (impedir una acción si falta el permiso)
 * en NewsManagement/EventsManagement/PartnersManagement queda fuera de esta
 * iteración — ver User::hasPermission() para el punto de consulta ya listo
 * para cuando se decida exigirlos.
 */
class AdminRoleService
{
    // ── Catálogo de permisos granulares — lista blanca fija y pequeña ────
    // (clave interna => etiqueta en español), agrupada por módulo para
    // alimentar directamente la grilla de checkboxes de la vista.
    private const PERMISOS_DISPONIBLES = [
        'Noticias' => [
            'noticias.crear'    => 'Crear',
            'noticias.editar'   => 'Editar (incluye inhabilitar)',
            'noticias.eliminar' => 'Eliminar',
        ],
        'Eventos' => [
            'eventos.crear'    => 'Crear',
            'eventos.editar'   => 'Editar (incluye cancelar)',
            'eventos.eliminar' => 'Eliminar',
        ],
        'Socios' => [
            'socios.crear'    => 'Registrar',
            'socios.editar'   => 'Editar',
            'socios.eliminar' => 'Eliminar',
        ],
        'Usuarios' => [
            'usuarios.gestionar' => 'Gestionar',
        ],
    ];

    // ── Claves de rol de la UI → nombre real del rol en BD ───────────────
    private const ROL_CLAVE_A_NOMBRE = [
        'usuario'       => AdminUsersRepository::ROL_USUARIO_NORMAL,
        'colaborador'   => AdminUsersRepository::ROL_COLABORADOR,
        'administrador' => AdminUsersRepository::ROL_ADMINISTRADOR,
    ];

    // ── Etiquetas de las claves de rol para los radios de la vista ───────
    private const ROL_OPCIONES = [
        'usuario'       => 'Usuario',
        'colaborador'   => 'Colaborador',
        'administrador' => 'Administrador',
    ];

    private const PUESTO_POR_DEFECTO = 'Administrador';

    public function __construct(
        private readonly AdminUsersRepository $usersRepository,
        private readonly AdminPartnersService $partnersService,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  OPCIONES PARA LA VISTA
    // ════════════════════════════════════════════════════════════════════

    /** @return array<string, string> */
    public function getRoleOptions(): array
    {
        return self::ROL_OPCIONES;
    }

    /** @return array<string, array<string, string>> Agrupado por módulo */
    public function getPermissionCatalog(): array
    {
        return self::PERMISOS_DISPONIBLES;
    }

    public function getDefaultPosition(): string
    {
        return self::PUESTO_POR_DEFECTO;
    }

    // ════════════════════════════════════════════════════════════════════
    //  LECTURA — precarga del modal
    // ════════════════════════════════════════════════════════════════════

    /**
     * Datos completos para precargar el modal "Administrar rol": rol actual,
     * puesto, permisos otorgados y — si aplica — su perfil de colaborador.
     * Todo leído directamente desde la BD.
     *
     * @return array{
     *   nombre: string, activo: bool, rolActual: string, puesto: string,
     *   permisos: array<int, string>, colaborador: array|null
     * }
     */
    public function getRoleManagementData(int $userId): array
    {
        $usuario   = $this->usersRepository->findWithRoles($userId);
        $rolActual = $usuario->roles->first();

        return [
            'nombre'      => $usuario->name,
            'activo'      => $usuario->active,
            'rolActual'   => $this->nombreRolAClave($rolActual?->name),
            'puesto'      => $rolActual?->pivot->position ?? self::PUESTO_POR_DEFECTO,
            'permisos'    => $rolActual?->pivot->permissions ?? [],
            'colaborador' => $this->partnersService->obtenerPerfilDeUsuario($userId),
        ];
    }

    /**
     * Estado activo/inactivo de un usuario, leído directamente desde la BD.
     * Usado como defensa en profundidad antes de EJECUTAR un cambio de rol
     * (la cuenta pudo haber sido inhabilitada después de abrir el modal).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getUserStatus(int $userId): bool
    {
        return $this->usersRepository->find($userId)->active;
    }

    // ════════════════════════════════════════════════════════════════════
    //  LECTURA — "Mi Perfil" (App\Livewire\Admin\MyProfile)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Datos del PROPIO perfil del usuario autenticado para la página "Mi
     * Perfil". A diferencia de getRoleManagementData() (que también carga
     * el perfil de colaborador, irrelevante aquí), esta lectura es deliberadamente
     * más liviana: "Mi Perfil" solo es accesible para Administradores, que
     * nunca tienen un perfil de socio vinculado.
     *
     * @return array{
     *   nombre: string, correo: string, fotoUrl: string|null, iniciales: string,
     *   puesto: string, semblanzaPublica: string, redesSociales: array<string, string|null>,
     *   mostrarEnQuienesSomos: bool
     * }
     */
    public function getMyProfileData(int $userId): array
    {
        $usuario = $this->usersRepository->findWithRoles($userId);
        $rol     = $usuario->roles->first();
        $pivote  = $rol?->pivot;

        return [
            'nombre'                => $usuario->name,
            'correo'                => $usuario->email,
            'fotoUrl'               => $usuario->profilePhotoUrl(),
            'iniciales'             => $usuario->getInitials(),
            'puesto'                => $pivote?->position ?? self::PUESTO_POR_DEFECTO,
            'semblanzaPublica'      => $pivote?->public_bio ?? '',
            'redesSociales'         => $pivote?->social_links ?? [],
            'mostrarEnQuienesSomos' => (bool) ($pivote?->show_in_about_us ?? false),
        ];
    }

    /**
     * Alterna si el perfil del usuario se muestra en "Quiénes Somos" →
     * "Nuestro Equipo". El nuevo valor se determina leyendo el estado ACTUAL
     * desde la BD — nunca se confía en lo que el cliente afirme.
     *
     * Si el usuario no tiene ningún rol asignado (no debería ocurrir: solo
     * se llama desde "Mi Perfil", accesible únicamente dentro del panel de
     * administración), no hace nada y devuelve false.
     *
     * NOTA: no invalida la caché de "Nuestro Equipo" (Cache::remember en
     * TeamSection, TTL 60 min) — mismo trade-off ya documentado ahí: el
     * cambio puede tardar hasta ese tiempo en reflejarse públicamente.
     *
     * @return bool El nuevo valor — evita que el componente tenga que volver a leer toda la fila.
     */
    public function toggleVisibilidadPublica(int $userId): bool
    {
        $usuario = $this->usersRepository->findWithRoles($userId);
        $rol     = $usuario->roles->first();

        if ($rol === null) {
            return false;
        }

        $nuevoValor = ! ($rol->pivot->show_in_about_us ?? false);

        $this->usersRepository->setShowInAboutUs($userId, $rol->id, $nuevoValor);

        return $nuevoValor;
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA — guardado atómico
    // ════════════════════════════════════════════════════════════════════

    /**
     * Guarda el cambio de rol completo de forma atómica:
     *  1. Si el rol elegido es 'colaborador': crea/actualiza su perfil de
     *     socio (logo, redes, etc.) y lo vincula a la cuenta.
     *  2. Sincroniza la asignación de rol — reemplaza cualquier rol previo
     *     por el elegido — guardando 'puesto'/'permisos' en su pivote
     *     (ambos quedan null/[] salvo que el rol sea 'administrador').
     *
     * Todo dentro de una transacción: si el perfil de colaborador falla al
     * guardarse, el rol tampoco cambia — evita un estado a medias.
     *
     * @param array<int, string>      $permisos        Claves recibidas del cliente — se filtran contra la lista blanca
     * @param array<string, mixed>|null $datosColaborador Forma exacta esperada por AdminPartnersService::guardar()
     * @throws \InvalidArgumentException si $rolClave no es una de las 3 claves válidas
     */
    public function guardarCambiosDeRol(
        int $userId,
        string $rolClave,
        ?string $puesto,
        array $permisos,
        ?array $datosColaborador,
        ?UploadedFile $logoColaborador,
        bool $eliminarLogoColaborador,
    ): void {
        if (! array_key_exists($rolClave, self::ROL_CLAVE_A_NOMBRE)) {
            throw new \InvalidArgumentException("Clave de rol inválida: {$rolClave}");
        }

        $roleId = $this->usersRepository->findRoleIdByName(self::ROL_CLAVE_A_NOMBRE[$rolClave]);

        // Puesto y permisos solo tienen sentido para Administrador — se
        // descartan al elegir otro rol para no dejar datos obsoletos que
        // pudieran reactivarse silenciosamente ante una futura promoción.
        $puestoFinal   = $rolClave === 'administrador' ? $puesto : null;
        $permisosFinal = $rolClave === 'administrador' ? $this->filtrarPermisosValidos($permisos) : [];

        DB::transaction(function () use (
            $userId, $roleId, $puestoFinal, $permisosFinal,
            $rolClave, $datosColaborador, $logoColaborador, $eliminarLogoColaborador
        ) {
            if ($rolClave === 'colaborador' && $datosColaborador !== null) {
                $perfilExistente = $this->partnersService->obtenerPerfilDeUsuario($userId);

                $socio = $this->partnersService->guardar(
                    socioId:      $perfilExistente['partnerId'] ?? null,
                    datos:        $datosColaborador,
                    logo:         $logoColaborador,
                    eliminarLogo: $eliminarLogoColaborador,
                );

                $this->partnersService->vincularConUsuario($socio->id, $userId);
            }

            $this->usersRepository->syncRole($userId, $roleId, $puestoFinal, $permisosFinal);
        });
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Traduce el nombre real del rol en BD a la clave interna de la UI. */
    private function nombreRolAClave(?string $nombreRol): string
    {
        return match ($nombreRol) {
            AdminUsersRepository::ROL_ADMINISTRADOR  => 'administrador',
            AdminUsersRepository::ROL_COLABORADOR    => 'colaborador',
            AdminUsersRepository::ROL_USUARIO_NORMAL => 'usuario',
            default                                   => 'usuario',
        };
    }

    /** Filtra las claves recibidas contra el catálogo real — defensa en profundidad. */
    private function filtrarPermisosValidos(array $permisos): array
    {
        $clavesValidas = array_merge(...array_values(array_map('array_keys', self::PERMISOS_DISPONIBLES)));

        return array_values(array_intersect($permisos, $clavesValidas));
    }
}
