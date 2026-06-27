<?php

namespace App\Livewire\Home;

use Livewire\Component;

/**
 * Sección CTA del home: logo destacado + acción según sesión.
 * Invitado → botón "Regístrate" (abre el modal global de registro).
 * Autenticado → mensaje de agradecimiento personalizado.
 *
 * Reemplaza a App\Livewire\Home\VolunteerForm (formulario de demostración,
 * sin persistencia real — ver commit que la introdujo).
 */
class SupportCtaSection extends Component
{
    /** Abre el modal de registro — mismo evento que escucha RegisterModal */
    public function abrirRegistro(): void
    {
        $this->dispatch('abrir-modal-registro');
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.home.support-cta-section');
    }
}
