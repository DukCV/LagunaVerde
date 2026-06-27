<?php

namespace App\Livewire\Events\EventDetail;

use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: carrusel de imágenes y videos del evento.
 *
 * RESPONSABILIDADES:
 *  - Renderizar los archivos multimedia recibidos como array plano.
 *  - La navegación del carrusel ocurre en Alpine.js (sin roundtrips).
 *  - No accede a la BD ni conoce el modelo Media.
 *
 * SEGURIDAD:
 *  - #[Locked] en $items y $eventTitle → sin tampering del cliente.
 *  - Las URLs vienen del DTO (procesadas por Storage::disk()->url()).
 *  - e() en atributos src en la vista → previene XSS en atributos HTML.
 */
class EventCarousel extends Component
{
    /**
     * Array de archivos multimedia serializados como arrays planos.
     * Cada elemento: ['url', 'mime', 'alt', 'title', 'isImage', 'isVideo']
     *
     * @var array<int, array<string, mixed>>
     */
    #[Locked]
    public array $items = [];

    /** Título del evento para texto alternativo accesible. */
    #[Locked]
    public string $eventTitle = '';

    public function mount(array $items = [], string $eventTitle = ''): void
    {
        $this->items      = $items;
        $this->eventTitle = mb_substr(strip_tags($eventTitle), 0, 220);
    }

    public function render()
    {
        return view('livewire.events.event-detail.event-carousel');
    }
}
