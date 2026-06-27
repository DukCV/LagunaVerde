<?php

namespace App\DTOs;

use App\Models\User;
use Livewire\Wireable;

/**
 * DTO de tarjeta de integrante del equipo — sección "Nuestro Equipo" de la
 * página pública "Quiénes Somos" (mismo patrón que App\DTOs\PartnerCardDto).
 *
 * RESPONSABILIDADES:
 *  - Única puerta de entrada de datos de User hacia la vista pública del equipo.
 *  - Limitar longitud de cada campo antes de exponerlo (defensa en profundidad).
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * SEGURIDAD XSS:
 *  La protección XSS se delega exclusivamente a Blade mediante {{ }}, que
 *  aplica htmlspecialchars() en cada punto de salida en la vista.
 *
 * SEGURIDAD DE ENLACES (defensa en profundidad):
 *  Los enlaces se revalidan aquí contra esquema http(s) al LEER de la BD —
 *  igual que AdminPartnerItemDto::sanitizeUrl() — y <x-social-links> los
 *  vuelve a revalidar al renderizar. Cualquier valor heredado de datos
 *  antiguos o cargado fuera del formulario que no sea http(s) se descarta.
 *
 * Wireable: permite usar este DTO como propiedad pública de un componente
 * Livewire (App\Livewire\About\TeamSection) — mismo patrón que PartnerCardDto
 * en App\Livewire\Home\CollaboratorsSection.
 */
readonly class TeamMemberDto implements Wireable
{
    public function __construct(
        public int     $id,          // uso interno (wire:key) — no hay página de detalle público
        public string  $name,
        public ?string $position,    // puesto en el pivote role_user (ej. "Director General")
        public string  $publicBio,
        public ?string $photoUrl,    // null si no hay foto de perfil — la vista usa iniciales
        public string  $initials,
        public ?string $website,
        public ?string $instagram,
        public ?string $facebook,
        public ?string $twitter,
        public ?string $linkedin,
        public ?string $youtube,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent cargado
    // ════════════════════════════════════════════════════════════════════

    /**
     * Transforma un modelo User (con la relación 'roles' precargada,
     * restringida al rol 'Administrador') en DTO.
     */
    public static function fromModel(User $user): self
    {
        $pivot   = $user->roles->first()?->pivot;
        $enlaces = $pivot?->social_links ?? [];

        return new self(
            id:         $user->id,
            name:       self::sanitize($user->name, 150),
            position:   $pivot?->position !== null ? self::sanitize($pivot->position, 100) : null,
            publicBio:  self::sanitize($pivot?->public_bio ?? '', 1000),
            photoUrl:   $user->profilePhotoUrl(),
            initials:   $user->getInitials(),
            website:    self::sanitizeUrl($enlaces['website'] ?? null),
            instagram:  self::sanitizeUrl($enlaces['instagram'] ?? null),
            facebook:   self::sanitizeUrl($enlaces['facebook'] ?? null),
            twitter:    self::sanitizeUrl($enlaces['twitter'] ?? null),
            linkedin:   self::sanitizeUrl($enlaces['linkedin'] ?? null),
            youtube:    self::sanitizeUrl($enlaces['youtube'] ?? null),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  WIREABLE — serialización entre peticiones de Livewire
    // ════════════════════════════════════════════════════════════════════

    public function toLivewire(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'position'   => $this->position,
            'publicBio'  => $this->publicBio,
            'photoUrl'   => $this->photoUrl,
            'initials'   => $this->initials,
            'website'    => $this->website,
            'instagram'  => $this->instagram,
            'facebook'   => $this->facebook,
            'twitter'    => $this->twitter,
            'linkedin'   => $this->linkedin,
            'youtube'    => $this->youtube,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(...$value);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Limita longitud máxima y normaliza espacios. */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }

    /**
     * Revalida el esquema del enlace al leerlo de la BD (defensa en
     * profundidad). Descarta cualquier valor que no comience con http(s)
     * — incluido "javascript:" u otros esquemas peligrosos — para que
     * nunca llegue a renderizarse en un atributo href.
     *
     * $value es 'mixed' (no '?string') a propósito: 'social_links' es JSON
     * decodificado a array — si algún valor llegara corrupto o con un tipo
     * inesperado, is_string() lo descarta en vez de provocar un TypeError.
     */
    private static function sanitizeUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_match('#^https?://#i', $value) ? $value : null;
    }
}
