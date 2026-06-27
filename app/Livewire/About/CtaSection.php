<?php

namespace App\Livewire\About;

use Livewire\Component;

class CtaSection extends Component
{
    public string $donateUrl = '/donate';

    /** Abre el modal de registro — mismo evento global que usa Home */
    public function abrirRegistro(): void
    {
        $this->dispatch('abrir-modal-registro');
    }

    public function render()
    {
        return view('livewire.about.cta-section');
    }
}
