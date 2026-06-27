<?php

namespace App\Livewire\Home;

use App\DTOs\Home\Events\EventCardDto;
use App\Services\Home\Events\EventService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: slider de próximos eventos en el home.
 *
 * Los imports apuntan a la estructura real de carpetas del proyecto:
 *   app/DTOs/Home/Events/EventCardDto.php
 *   app/Services/Home/Events/EventService.php
 */
class EventsSection extends Component
{
    /**
     * DTOs serializados como arrays planos.
     * @var array[]
     */
    #[Locked]
    public array $events = [];

    /** Índice del slide activo (0-based). */
    public int $currentSlide = 0;

    // ════════════════════════════════════════════════════════════════════
    //  LIFECYCLE
    // ════════════════════════════════════════════════════════════════════

    public function mount(EventService $service): void
    {
        try {
            $this->events = array_map(
                fn (EventCardDto $dto) => $dto->toLivewire(),
                $service->getUpcoming(max: 3)
            );
        } catch (\Throwable) {
            // Error de BD → estado vacío sin exponer detalles al usuario
            $this->events = [];
        }

        $this->currentSlide = 0;
    }

    // ════════════════════════════════════════════════════════════════════
    //  COMPUTED — evento actualmente visible
    // ════════════════════════════════════════════════════════════════════

    #[Computed]
    public function currentEvent(): ?EventCardDto
    {
        if (empty($this->events)) {
            return null;
        }

        return EventCardDto::fromLivewire(
            $this->events[$this->safeIndex($this->currentSlide)]
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES — navegación
    // ════════════════════════════════════════════════════════════════════

    public function next(): void
    {
        if (empty($this->events)) {
            return;
        }
        $this->currentSlide = ($this->currentSlide + 1) % count($this->events);
    }

    public function prev(): void
    {
        if (empty($this->events)) {
            return;
        }
        $this->currentSlide = ($this->currentSlide - 1 + count($this->events)) % count($this->events);
    }

    /** Salta a un slide validando el índice — previene tampering del cliente. */
    public function goToSlide(int $index): void
    {
        $this->currentSlide = $this->safeIndex($index);
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.home.events-section');
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /** Índice seguro dentro del rango real de eventos. */
    private function safeIndex(int $index): int
    {
        if (empty($this->events)) {
            return 0;
        }
        return max(0, min($index, count($this->events) - 1));
    }
}
