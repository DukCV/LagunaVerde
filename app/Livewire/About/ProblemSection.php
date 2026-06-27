<?php

namespace App\Livewire\About;

use Livewire\Component;

class ProblemSection extends Component
{
    public array $stats = [];
    public array $facts = [];

    public function mount()
    {
        // Datos temporales
        $this->stats = [
            [
                'icon' => 'droplet',
                'value' => '65%',
                'label' => 'Contaminación del Agua',
                'description' => 'Niveles críticos de contaminantes químicos y orgánicos',
                'color' => 'red'
            ],
            [
                'icon' => 'trend',
                'value' => '42%',
                'label' => 'Pérdida de Biodiversidad',
                'description' => 'Especies nativas desaparecidas en la última década',
                'color' => 'orange'
            ],
            [
                'icon' => 'factory',
                'value' => '8',
                'label' => 'Fuentes Industriales',
                'description' => 'Empresas que descargan residuos sin tratamiento',
                'color' => 'purple'
            ],
            [
                'icon' => 'trash',
                'value' => '15 ton',
                'label' => 'Residuos Mensuales',
                'description' => 'Basura arrojada a la laguna cada mes',
                'color' => 'yellow'
            ],
        ];

        $this->facts = [
            [
                'title' => 'Crisis de Oxígeno',
                'text' => 'Los niveles de oxígeno disuelto han caído un 60%, amenazando la vida acuática.'
            ],
            [
                'title' => 'Proliferación de Algas',
                'text' => 'El exceso de nutrientes ha provocado floraciones tóxicas que cubren el 40% de la superficie.'
            ],
            [
                'title' => 'Pérdida de Manglares',
                'text' => '30 hectáreas de manglar han sido destruidas, eliminando hábitats críticos.'
            ],
        ];
    }

    public function render()
    {
        return view('livewire.about.problem-section');
    }
}
