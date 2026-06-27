<?php

namespace App\DTOs\Admin;

use App\Models\User;
use App\Repositories\Admin\AdminUsersRepository;

/**
 * DTO de ítem de usuario para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada de datos del modelo User a la vista admin.
 *  - Sanitizar cada campo antes de exponerlo para prevenir XSS en salida.
 *  - Resolver el rol principal y su color de badge una sola vez, para que la
 *    vista nunca repita esta lógica de negocio.
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * SEGURIDAD:
 *  - Nunca expone 'password' ni 'remember_token' (no forman parte de este DTO).
 *  - avatarUrl proviene únicamente de User::profilePhotoUrl(), que construye
 *    la URL a partir del disco 'public' configurado en el servidor — nunca de
 *    una ruta enviada por el cliente.
 */
readonly class AdminUserItemDto
{
    public function __construct(
        public int     $id,             // Solo para operaciones internas de admin (wire:key)
        public string  $uuid,           // Identificador público (reservado para uso futuro)
        public string  $name,
        public string  $email,
        public string  $phoneLabel,
        public string  $roleLabel,
        public string  $roleKey,        // 'administrador' | 'colaborador' | 'normal' | 'sin-rol' — color del badge
        public bool    $active,
        public string  $statusLabel,    // 'Activo' | 'Inactivo'
        public ?string $avatarUrl,
        public string  $initials,
        public string  $createdAtLabel, // Fecha de registro, formato d/m/Y
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent cargado
    // ════════════════════════════════════════════════════════════════════

    /**
     * Transforma un modelo User en DTO.
     *
     * El modelo debe llegar con eager loading de 'roles:id,name' para evitar
     * una consulta extra por fila al resolver el rol principal.
     *
     * SEGURIDAD XSS:
     *  - La protección XSS se delega exclusivamente a Blade mediante {{ }}.
     *  - mb_substr() limita longitud máxima → evita strings gigantes en memoria.
     */
    public static function fromModel(User $user): self
    {
        $rolPrincipal = $user->roles->first()?->name;

        return new self(
            id:             $user->id,
            uuid:           $user->uuid,
            name:           self::sanitize($user->name, 150),
            email:          self::sanitize($user->email, 190),
            phoneLabel:     $user->phone !== null ? self::sanitize($user->phone, 30) : 'Sin teléfono',
            roleLabel:      $rolPrincipal ?? 'Sin rol asignado',
            roleKey:        self::resolveRoleKey($rolPrincipal),
            active:         (bool) $user->active,
            statusLabel:    $user->active ? 'Activo' : 'Inactivo',
            avatarUrl:      $user->profilePhotoUrl(),
            initials:       $user->getInitials(),
            createdAtLabel: $user->created_at->format('d/m/Y'),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Traduce el nombre de rol a la clave interna usada por el badge en Blade */
    private static function resolveRoleKey(?string $roleName): string
    {
        return match ($roleName) {
            AdminUsersRepository::ROL_ADMINISTRADOR  => 'administrador',
            AdminUsersRepository::ROL_COLABORADOR    => 'colaborador',
            AdminUsersRepository::ROL_USUARIO_NORMAL => 'normal',
            default                                   => 'sin-rol',
        };
    }

    /**
     * Elimina HTML, normaliza espacios y limita longitud máxima.
     * XSS prevenido por Blade con {{ }} en cada punto de salida de la vista.
     */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $maxLength);
    }
}
