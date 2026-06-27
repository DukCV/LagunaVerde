<?php

namespace App\Livewire;

use Livewire\Component;

class ProblemSection extends Component
{
    public array $problems = [
        [
            'icon' => 'droplet',
            'color' => 'text-blue-600',
            'title' => 'Contaminación del Agua',
            'stat' => '65%',
            'description' => 'de la laguna presenta niveles críticos de contaminación por residuos industriales y domésticos.',
            'severity' => 'critical',
        ],
        [
            'icon' => 'fish',
            'color' => 'text-orange-600',
            'title' => 'Pérdida de Biodiversidad',
            'stat' => '42%',
            'description' => 'de las especies nativas han desaparecido en la última década debido a la degradación del hábitat.',
            'severity' => 'high',
        ],
        [
            'icon' => 'tree',
            'color' => 'text-green-600',
            'title' => 'Deforestación',
            'stat' => '30%',
            'description' => 'de la vegetación ribereña ha sido eliminada, aumentando la erosión y sedimentación.',
            'severity' => 'medium',
        ],
    ];

    public function render()
    {
        return view('livewire.problem-section');
    }
}
