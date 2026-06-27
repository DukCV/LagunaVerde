<?php

namespace App\DTOs\Admin;

use App\Models\Partner;

/**
 * DTO ligero de socio colaborador para el selector de "Colaboradores
 * invitados" del formulario de eventos (App\Livewire\Admin\Events\EventForm).
 *
 * Deliberadamente mínimo (id, nombre, logo) — a diferencia de
 * AdminPartnerItemDto, esta vista solo necesita lo justo para pintar una
 * tarjeta pequeña en la grilla de selección, no el detalle completo del socio.
 *
 * SEGURIDAD XSS: la protección se delega a Blade ({{ }}) en el punto de
 * salida; este DTO solo limita longitud (defensa en profundidad).
 */
readonly class PartnerPickerItemDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $logoUrl,
    ) {}

    /** Transforma un modelo Partner (con la relación 'media' precargada) en DTO. */
    public static function fromModel(Partner $partner): self
    {
        $logo = $partner->media->firstWhere('collection', 'logo');

        return new self(
            id: $partner->id,
            name: mb_substr(trim($partner->name), 0, 150),
            logoUrl: $logo?->url(),
        );
    }
}
