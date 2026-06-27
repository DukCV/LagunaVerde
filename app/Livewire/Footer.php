<?php

namespace App\Livewire;

use Livewire\Component;

class Footer extends Component
{
    public $quickLinks = [
        ['label' => 'Inicio', 'page' => 'home'],
        ['label' => 'Quiénes Somos', 'page' => 'about'],
        ['label' => 'Noticias', 'page' => 'news'],
        ['label' => 'Eventos', 'page' => 'events'],
        ['label' => 'Contacto', 'page' => 'contact'],
    ];

    public $legalLinks = [
        ['label' => 'Aviso de Privacidad', 'href' => '#privacidad'],
        ['label' => 'Términos y Condiciones', 'href' => '#terminos'],
        ['label' => 'Transparencia', 'href' => '#transparencia'],
        ['label' => 'Código de Ética', 'href' => '#etica'],
    ];

    public $socialLinks = [
        ['label' => 'Facebook', 'href' => 'https://www.facebook.com/profile.php/?id=61553786062534'],
    ];

    public function navigate($page, $hash = null)
    {
        if ($hash) {
            $this->dispatch('scroll-to', id: $hash);
        } else {
            $this->dispatch('scroll-top');
        }
    }

    public function render()
    {
        return view('livewire.footer', [
            'year' => now()->year
        ]);
    }
}
