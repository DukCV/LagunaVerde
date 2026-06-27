<?php

namespace App\Livewire;

use App\Services\Home\SpotlightService;
use Livewire\Component;

class HeroSection extends Component
{
    public function donate()
    {
        // Aquí puedes redirigir, abrir modal o emitir evento
        // $this->dispatch('open-donation-modal');
        logger('Hero donate clicked');
    }

    public function render(SpotlightService $service)
    {
        return view('livewire.hero-section', [
            // Última noticia o próximo evento, al azar — ver SpotlightService
            'spotlight' => $service->obtenerTarjeta(),
        ]);
    }
}
