<?php

namespace App\Livewire\Events;

use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: banner del evento más próximo destacado.
 *
 * RESPONSABILIDADES:
 *  - Renderizar el array serializado de FeaturedEventDto.
 *  - Sin acceso a BD ni lógica de negocio.
 *
 * SEGURIDAD:
 *  - #[Locked] en $event → sin tampering del cliente.
 *  - Recibe array plano desde EventsIndex — nunca consulta BD directamente.
 */
class FeaturedEventBanner extends Component
{
    /**
     * Array plano del FeaturedEventDto serializado.
     * @var array<string, mixed>
     */
    #[Locked]
    public array $event = [];

    public function render()
    {
        return view('livewire.events.featured-event-banner');
    }
}
