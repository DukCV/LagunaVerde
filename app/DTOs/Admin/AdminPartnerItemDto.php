<?php

namespace App\DTOs\Admin;

use App\Models\Partner;

/**
 * DTO de ítem de socio colaborador para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada de datos del modelo Partner a las vistas admin.
 *  - Limitar longitud de cada campo antes de exponerlo (defensa en profundidad).
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * SEGURIDAD XSS:
 *  La protección XSS se delega exclusivamente a Blade mediante {{ }}, que
 *  aplica htmlspecialchars() en cada punto de salida en la vista.
 *
 * SEGURIDAD DE ENLACES (defensa en profundidad):
 *  PartnerForm::reglaEsquemaSeguro() ya exige esquema http(s) al guardar,
 *  pero este DTO vuelve a validarlo al LEER de la BD vía sanitizeUrl().
 *  Cualquier valor heredado de datos antiguos o cargado fuera del formulario
 *  (importación, semilla, edición directa en BD) que no sea http(s) se
 *  descarta como null en vez de renderizarse en un href.
 */
readonly class AdminPartnerItemDto
{
    public function __construct(
        public int     $id,
        public string  $name,
        public string  $type,
        public bool    $active,
        public string  $createdAt,    // Formateada: "15 Ene 2025"
        public string  $updatedAt,    // Formateada: "15 Ene 2025"
        public string  $whoTheyAre,
        public string  $howTheySupport,
        public ?string $website,
        public ?string $instagram,
        public ?string $facebook,
        public ?string $twitter,
        public ?string $linkedin,
        public ?string $youtube,
        public ?string $logoUrl,      // null si no hay logo registrado
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
            id:              $partner->id,
            name:            self::sanitize($partner->name, 150),
            type:            $partner->type,
            active:          $partner->active,
            createdAt:       $partner->created_at->translatedFormat('d M Y'),
            updatedAt:       $partner->updated_at->translatedFormat('d M Y'),
            whoTheyAre:      self::sanitize($partner->who_they_are, 600),
            howTheySupport:  self::sanitize($partner->how_they_support, 600),
            website:         self::sanitizeUrl($partner->website),
            instagram:       self::sanitizeUrl($partner->social_instagram),
            facebook:        self::sanitizeUrl($partner->social_facebook),
            twitter:         self::sanitizeUrl($partner->social_twitter),
            linkedin:        self::sanitizeUrl($partner->social_linkedin),
            youtube:         self::sanitizeUrl($partner->social_youtube),
            logoUrl:         $logo?->url(),
        );
    }

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
     */
    private static function sanitizeUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_match('#^https?://#i', $value) ? $value : null;
    }
}
