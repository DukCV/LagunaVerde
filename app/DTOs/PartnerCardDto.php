<?php

namespace App\DTOs;

use App\Models\Partner;
use Livewire\Wireable;

/**
 * DTO de tarjeta de socio colaborador — compartido por el listado público
 * (/colaboradores) y la sección "Nuestros Colaboradores" del home/Quiénes
 * Somos (mismo componente <x-collaborators.card> en ambos lugares, DRY).
 *
 * RESPONSABILIDADES:
 *  - Única puerta de entrada de datos de Partner hacia las vistas públicas.
 *  - Limitar longitud de cada campo antes de exponerlo (defensa en profundidad).
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * SEGURIDAD XSS:
 *  La protección XSS se delega exclusivamente a Blade mediante {{ }}, que
 *  aplica htmlspecialchars() en cada punto de salida en la vista.
 *
 * Wireable: permite usar este DTO como propiedad pública de un componente
 * Livewire (App\Livewire\Home\CollaboratorsSection) — mismo patrón ya usado
 * en App\DTOs\Home\Events\EventCardDto. Sin esta interfaz, Livewire no sabe
 * serializar/deserializar el objeto entre peticiones.
 *
 * Deliberadamente separado de AdminPartnerItemDto: el contexto admin y el
 * público pueden evolucionar de forma independiente sin acoplarse entre sí
 * (mismo patrón usado entre NewsCardDto y los DTOs administrativos de noticias).
 */
readonly class PartnerCardDto implements Wireable
{
    public function __construct(
        public int     $id,     // uso interno (wire:key / modal de detalles) — Partner no tiene página de detalle público
        public string  $name,
        public string  $type,
        public string  $whoTheyAre,      // texto completo — la tarjeta lo recorta visualmente con line-clamp
        public string  $howTheySupport,  // texto completo — la tarjeta lo recorta visualmente con line-clamp
        public string  $createdAt,   // Formateada: "15 Ene 2025"
        public string  $updatedAt,   // Formateada: "15 Ene 2025"
        public ?string $logoUrl,     // null si no hay logo registrado
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
     * Transforma un modelo Partner (con la relación 'media' precargada) en DTO.
     */
    public static function fromModel(Partner $partner): self
    {
        $logo = $partner->media->firstWhere('collection', 'logo');

        return new self(
            id:             $partner->id,
            name:           self::sanitize($partner->name, 150),
            type:           $partner->type,
            whoTheyAre:     self::sanitize($partner->who_they_are, 1000),
            howTheySupport: self::sanitize($partner->how_they_support, 1000),
            createdAt:      $partner->created_at->translatedFormat('d M Y'),
            updatedAt:      $partner->updated_at->translatedFormat('d M Y'),
            logoUrl:        $logo?->url(),
            website:        $partner->website,
            instagram:      $partner->social_instagram,
            facebook:       $partner->social_facebook,
            twitter:        $partner->social_twitter,
            linkedin:       $partner->social_linkedin,
            youtube:        $partner->social_youtube,
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  WIREABLE — serialización entre peticiones de Livewire
    // ════════════════════════════════════════════════════════════════════

    public function toLivewire(): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type,
            'whoTheyAre'     => $this->whoTheyAre,
            'howTheySupport' => $this->howTheySupport,
            'createdAt'      => $this->createdAt,
            'updatedAt'      => $this->updatedAt,
            'logoUrl'        => $this->logoUrl,
            'website'        => $this->website,
            'instagram'      => $this->instagram,
            'facebook'       => $this->facebook,
            'twitter'        => $this->twitter,
            'linkedin'       => $this->linkedin,
            'youtube'        => $this->youtube,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(...$value);
    }

    /** Limita longitud máxima y normaliza espacios. */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }
}
