<?php

namespace App\DTOs\Admin;

use App\Models\User;

/**
 * DTO de ítem de usuario para el selector de "vincular usuario" del
 * formulario de socios colaboradores (mismo patrón que PartnerPickerItemDto,
 * usado por EventForm para su selector de colaboradores invitados).
 *
 * SEGURIDAD:
 *  - Sanitiza name/email antes de exponerlos (defensa adicional; Blade ya
 *    escapa con {{ }} en cada punto de salida).
 *  - avatarUrl proviene únicamente de User::profilePhotoUrl(), nunca de una
 *    ruta enviada por el cliente.
 */
readonly class UserPickerItemDto
{
    public function __construct(
        public int     $id,
        public string  $name,
        public string  $email,
        public ?string $avatarUrl,
        public string  $initials,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id:        $user->id,
            name:      self::sanitize($user->name, 150),
            email:     self::sanitize($user->email, 190),
            avatarUrl: $user->profilePhotoUrl(),
            initials:  $user->getInitials(),
        );
    }

    /** Elimina HTML y limita longitud — XSS prevenido finalmente por Blade con {{ }}. */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $maxLength);
    }
}
