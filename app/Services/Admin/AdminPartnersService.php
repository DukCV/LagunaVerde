<?php

namespace App\Services\Admin;

use App\DTOs\Admin\AdminPartnerItemDto;
use App\DTOs\Admin\PartnerPickerItemDto;
use App\DTOs\Admin\UserPickerItemDto;
use App\Models\Partner;
use App\Repositories\Admin\AdminPartnersRepository;
use App\Repositories\Admin\AdminUsersRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Servicio de gestión de socios colaboradores para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas al AdminPartnersRepository.
 *  - Transformar colecciones de modelos Eloquent en DTOs listos para la vista.
 *  - Construir los arrays de opciones para los selectores de filtro.
 *  - Ejecutar el guardado (creación/edición) y la eliminación dentro de transacciones.
 *  - Sincronizar, dentro de esa misma transacción, el vínculo socio↔usuario
 *    y el rol del usuario afectado (ver guardar()/sincronizarUsuarioVinculado()).
 *
 * El componente Livewire no conoce ni el modelo Partner ni el repositorio;
 * solo trabaja con DTOs y arrays de opciones primitivos (mismo patrón que AdminNewsService).
 *
 * DEPENDENCIA EN AdminUsersRepository:
 *  Necesaria para sincronizar el rol del usuario vinculado/desvinculado. Se
 *  inyecta el repositorio (no AdminUsersService ni AdminRoleService) porque
 *  AdminRoleService ya depende de este servicio para el flujo "Administrar
 *  rol" → depender de él aquí crearía un ciclo de inyección irresoluble.
 */
class AdminPartnersService
{
    public function __construct(
        private readonly AdminPartnersRepository $repository,
        private readonly AdminUsersRepository $usersRepository,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por PartnersManagement
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador de socios con cada ítem transformado a AdminPartnerItemDto.
     *
     * @return LengthAwarePaginator<AdminPartnerItemDto>
     */
    public function getPaginatedPartners(
        string $search,
        string $type,
        string $status,
        string $sortBy,
        int    $perPage = 12
    ): LengthAwarePaginator {
        $paginator = $this->repository->paginate($search, $type, $status, $sortBy, $perPage);

        return $paginator->through(
            fn (Partner $partner) => AdminPartnerItemDto::fromModel($partner)
        );
    }

    /** Total de socios visibles públicamente, para la métrica del encabezado. */
    public function getActiveCount(): int
    {
        return $this->repository->countActive();
    }

    /**
     * Opciones del selector de tipo [valor => etiqueta], incluida "Todos los tipos".
     * Partner::TYPES es una lista fija y pequeña — no requiere consultar la BD.
     *
     * @return array<string, string>
     */
    public function getTypeOptions(): array
    {
        return collect(['todos' => 'Todos los tipos'])
            ->merge(collect(Partner::TYPES)->mapWithKeys(fn ($type) => [$type => $type]))
            ->toArray();
    }

    /** Opciones del selector de estado de visibilidad [valor => etiqueta]. */
    public function getStatusOptions(): array
    {
        return [
            'todos'     => 'Todos los estados',
            'activos'   => 'Activos',
            'inactivos' => 'Inactivos',
        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por EventForm (selector de colaboradores invitados)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Búsqueda ligera de socios activos para el selector de "Colaboradores
     * invitados" del formulario de eventos. Vive aquí (no en
     * AdminEventsFormService) porque toda la lógica de acceso a Partner
     * debe pasar por este servicio — ningún otro módulo consulta
     * AdminPartnersRepository directamente.
     *
     * @param int[] $excludeIds
     * @return array<int, PartnerPickerItemDto>
     */
    public function searchActiveForPicker(string $search, string $type, array $excludeIds, int $limit = 12): array
    {
        return $this->repository->searchActiveForPicker($search, $type, $excludeIds, $limit)
            ->map(fn (Partner $partner) => PartnerPickerItemDto::fromModel($partner))
            ->all();
    }

    /** Un solo socio activo, para validar+resolver un ID antes de añadirlo a un evento. */
    public function findActiveForPicker(int $id): ?PartnerPickerItemDto
    {
        $partner = $this->repository->findActiveForPicker($id);

        return $partner ? PartnerPickerItemDto::fromModel($partner) : null;
    }

    /**
     * Alterna la visibilidad pública de un socio.
     * El nuevo estado se determina leyendo el estado ACTUAL desde la BD —
     * nunca se confía en el estado enviado por el cliente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function toggleStatus(int $id): void
    {
        $activo = $this->repository->find($id)->active;

        $this->repository->toggleActive($id, ! $activo);
    }

    /**
     * Devuelve el estado de visibilidad actual de un socio directamente desde la BD.
     * Usado por el componente para decidir qué texto mostrar en el modal de confirmación.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getPartnerStatus(int $id): bool
    {
        return $this->repository->find($id)->active;
    }

    /**
     * Devuelve el nombre de un socio directamente desde la BD.
     * Usado para construir el texto dinámico del modal de confirmación de eliminación.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function getPartnerName(int $id): string
    {
        return $this->repository->find($id)->name;
    }

    /**
     * Datos del perfil de socio vinculado a un usuario (rol 'Colaborador'),
     * listos para precargar el formulario en "Administrar rol". Devuelve
     * null si el usuario todavía no tiene un perfil de socio creado.
     *
     * @return array{partnerId: int, nombre: string, tipo: string, sitioWeb: string,
     *   redInstagram: string, redFacebook: string, redTwitter: string,
     *   redLinkedin: string, redYoutube: string, quienesSon: string,
     *   comoApoyan: string, logoUrl: string|null}|null
     */
    public function obtenerPerfilDeUsuario(int $userId): ?array
    {
        $socio = $this->repository->findByUserId($userId);

        if ($socio === null) {
            return null;
        }

        $logo = $socio->media->firstWhere('collection', 'logo');

        return [
            'partnerId'    => $socio->id,
            'nombre'       => $socio->name,
            'tipo'         => $socio->type,
            'sitioWeb'     => $socio->website ?? '',
            'redInstagram' => $socio->social_instagram ?? '',
            'redFacebook'  => $socio->social_facebook ?? '',
            'redTwitter'   => $socio->social_twitter ?? '',
            'redLinkedin'  => $socio->social_linkedin ?? '',
            'redYoutube'   => $socio->social_youtube ?? '',
            'quienesSon'   => $socio->who_they_are,
            'comoApoyan'   => $socio->how_they_support,
            'logoUrl'      => $logo?->url(),
        ];
    }

    /**
     * Vincula un socio (recién creado o ya existente) con la cuenta de
     * usuario propietaria. Operación idempotente y barata — UPDATE directo.
     */
    public function vincularConUsuario(int $partnerId, int $userId): void
    {
        $this->repository->linkToUser($partnerId, $userId);
    }

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — selector "Vincular usuario" del formulario de socios
    // ════════════════════════════════════════════════════════════════════

    /**
     * Búsqueda ligera de usuarios para el selector "Pertenece a un usuario"
     * del formulario de socios. $currentPartnerId permite que el usuario
     * YA vinculado al socio que se está editando siga apareciendo si se
     * vuelve a buscar (ver AdminUsersRepository::searchForPartnerLinking()).
     *
     * @return array<int, UserPickerItemDto>
     */
    public function buscarUsuariosParaVincular(string $search, ?int $currentPartnerId, int $limit = 8): array
    {
        return $this->usersRepository->searchForPartnerLinking($search, $currentPartnerId, $limit)
            ->map(fn ($user) => UserPickerItemDto::fromModel($user))
            ->all();
    }

    /** Un solo usuario por su ID, para la tarjeta de "usuario seleccionado". */
    public function obtenerUsuarioParaVincular(int $userId): ?UserPickerItemDto
    {
        $user = $this->usersRepository->findBasicInfo($userId);

        return $user ? UserPickerItemDto::fromModel($user) : null;
    }

    /**
     * Elimina permanentemente un socio junto con su logotipo y el archivo físico asociado.
     *
     * ARQUITECTURA DE TRANSACCIONES: las eliminaciones de registros de BD ocurren
     * DENTRO de DB::transaction; el archivo físico del disco solo se borra DESPUÉS
     * de que la transacción confirme con éxito (mismo patrón que AdminNewsService).
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException si el ID no existe
     */
    public function eliminarSocio(int $id): void
    {
        $archivosAEliminar = [];

        DB::transaction(function () use ($id, &$archivosAEliminar): void {
            // findOrFail garantiza existencia y aborta la transacción si no existe
            $this->repository->find($id);

            $archivosAEliminar = $this->repository->deleteWithCascade($id);
        });

        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            Storage::disk($disk)->delete($path);
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por PartnerForm
    // ════════════════════════════════════════════════════════════════════

    /**
     * Carga todos los datos del socio para pre-rellenar el formulario de edición.
     *
     * @return array{
     *   nombre: string, tipo: string, activo: bool,
     *   sitioWeb: string, redInstagram: string, redFacebook: string,
     *   redTwitter: string, redLinkedin: string, redYoutube: string,
     *   quienesSon: string, comoApoyan: string, logoUrl: string|null,
     *   userId: int|null
     * }
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function obtenerParaEdicion(int $id): array
    {
        $socio = $this->repository->find($id);
        $logo  = $socio->media->firstWhere('collection', 'logo');

        return [
            'nombre'       => $socio->name,
            'tipo'         => $socio->type,
            'activo'       => $socio->active,
            'sitioWeb'     => $socio->website ?? '',
            'redInstagram' => $socio->social_instagram ?? '',
            'redFacebook'  => $socio->social_facebook ?? '',
            'redTwitter'   => $socio->social_twitter ?? '',
            'redLinkedin'  => $socio->social_linkedin ?? '',
            'redYoutube'   => $socio->social_youtube ?? '',
            'quienesSon'   => $socio->who_they_are,
            'comoApoyan'   => $socio->how_they_support,
            'logoUrl'      => $logo?->url(),
            'userId'       => $socio->user_id,
        ];
    }

    /**
     * Crea o actualiza un socio junto con su logotipo y, opcionalmente, su
     * vínculo con una cuenta de usuario (y el rol de esa cuenta).
     *
     * FLUJO:
     *  1. Abrir transacción DB.
     *  2. Crear / actualizar el registro Partner (sin 'user_id': eso lo
     *     gestiona el paso 4, nunca los atributos de texto del formulario).
     *  3. Si se solicitó eliminar el logo o se subió uno nuevo: borrar el actual.
     *  4. Si se subió un logo nuevo: almacenarlo y registrar el media.
     *  5. Si $usuarioVinculadoAnteriorId o $usuarioVinculadoNuevoId vienen
     *     informados: sincronizar vínculo + rol (ver sincronizarUsuarioVinculado()).
     *  6. Confirmar transacción.
     *  7. Eliminar archivos físicos obsoletos post-transacción (no revertibles).
     *
     * COMPATIBILIDAD: los dos parámetros de vínculo son opcionales y, por
     * defecto, null — los llamados existentes (PartnerForm sin la sección de
     * vínculo, AdminRoleService) no se ven afectados en absoluto: el bloque
     * de sincronización ni se ejecuta si ambos llegan en null.
     *
     * @throws \Throwable si la transacción de BD falla
     */
    public function guardar(
        ?int          $socioId,
        array         $datos,
        ?UploadedFile $logo,
        bool          $eliminarLogo,
        ?int          $usuarioVinculadoAnteriorId = null,
        ?int          $usuarioVinculadoNuevoId = null,
    ): Partner {
        $archivosAEliminar = [];

        $socio = DB::transaction(function () use (
            $socioId, $datos, $logo, $eliminarLogo, &$archivosAEliminar,
            $usuarioVinculadoAnteriorId, $usuarioVinculadoNuevoId
        ) {
            $atributos = [
                'name'              => trim($datos['nombre']),
                'type'              => $datos['tipo'],
                'active'            => $datos['activo'],
                'website'           => $datos['sitioWeb'] ?: null,
                'social_instagram'  => $datos['redInstagram'] ?: null,
                'social_facebook'   => $datos['redFacebook'] ?: null,
                'social_twitter'    => $datos['redTwitter'] ?: null,
                'social_linkedin'   => $datos['redLinkedin'] ?: null,
                'social_youtube'    => $datos['redYoutube'] ?: null,
                'who_they_are'      => trim($datos['quienesSon']),
                'how_they_support'  => trim($datos['comoApoyan']),
            ];

            if ($socioId === null) {
                $socio = $this->repository->create($atributos);
            } else {
                $this->repository->update($socioId, $atributos);
                $socio = $this->repository->find($socioId);
            }

            // ── Gestionar logotipo ─────────────────────────────────────
            if ($eliminarLogo || $logo !== null) {
                $archivosAEliminar = array_merge(
                    $archivosAEliminar,
                    $this->repository->logoPaths($socio->id)
                );
                $this->repository->deleteLogo($socio->id);
            }

            if ($logo !== null) {
                $ruta = $logo->store('partners/logos', 'public');
                $socio->media()->create([
                    'collection' => 'logo',
                    'disk'       => 'public',
                    'path'       => $ruta,
                    'mime'       => $logo->getMimeType(),
                    'size'       => $logo->getSize(),
                    'title'      => $atributos['name'],
                    'alt'        => 'Logo de ' . $atributos['name'],
                    'order'      => 0,
                ]);
            }

            // ── Vínculo con usuario + sincronización de rol ──────────────
            if ($usuarioVinculadoAnteriorId !== null || $usuarioVinculadoNuevoId !== null) {
                $this->sincronizarUsuarioVinculado($socio->id, $usuarioVinculadoAnteriorId, $usuarioVinculadoNuevoId);

                // linkToUser()/unlinkUser() son UPDATE directos por query
                // builder: no pasan por este modelo Eloquent ya cargado en
                // memoria. Sin esto, $socio->user_id quedaría desactualizado
                // en el valor que devuelve guardar() pese a ser correcto en BD.
                $socio->user_id = $usuarioVinculadoNuevoId;
            }

            return $socio;
        });

        // Eliminar archivos físicos TRAS confirmar la transacción — nunca antes,
        // para no perder archivos recuperables ante un rollback de BD.
        foreach ($archivosAEliminar as ['disk' => $disk, 'path' => $path]) {
            Storage::disk($disk)->delete($path);
        }

        return $socio;
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Sincroniza el vínculo socio↔usuario y el rol del usuario afectado.
     * Debe llamarse SOLO desde dentro de la transacción de guardar().
     *
     * REGLAS:
     *  - Si había un usuario vinculado antes y ahora cambia (a otro o a
     *    ninguno): su rol vuelve a 'Usuario Normal' — ya no es colaborador.
     *  - Si hay un usuario vinculado ahora (nuevo o el mismo de antes): el
     *    socio queda vinculado a él y, solo si es un vínculo NUEVO, su rol
     *    se establece en 'Colaborador' (si ya era el vinculado, no se repite
     *    la sincronización de rol — evita un UPDATE redundante).
     *  - Si no hay usuario vinculado ahora: el socio queda desvinculado.
     */
    private function sincronizarUsuarioVinculado(int $partnerId, ?int $usuarioAnteriorId, ?int $usuarioNuevoId): void
    {
        if ($usuarioAnteriorId !== null && $usuarioAnteriorId !== $usuarioNuevoId) {
            $rolNormalId = $this->usersRepository->findRoleIdByName(AdminUsersRepository::ROL_USUARIO_NORMAL);
            $this->usersRepository->syncRole($usuarioAnteriorId, $rolNormalId, null, []);
        }

        if ($usuarioNuevoId !== null) {
            $this->repository->linkToUser($partnerId, $usuarioNuevoId);

            if ($usuarioNuevoId !== $usuarioAnteriorId) {
                $rolColaboradorId = $this->usersRepository->findRoleIdByName(AdminUsersRepository::ROL_COLABORADOR);
                $this->usersRepository->syncRole($usuarioNuevoId, $rolColaboradorId, null, []);
            }
        } else {
            $this->repository->unlinkUser($partnerId);
        }
    }
}
