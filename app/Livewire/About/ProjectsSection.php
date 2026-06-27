<?php

namespace App\Livewire\About;

use Livewire\Component;

class ProjectsSection extends Component
{
    public array $achievements = [];

    public function mount()
    {
        $this->achievements = [
            [
                'icon' => 'recycle',
                'value' => '15',
                'unit' => 'Toneladas',
                'label' => 'Residuos Retirados',
                'description' => 'Limpieza masiva de basura de la laguna',
            ],
            [
                'icon' => 'tree',
                'value' => '5,000',
                'unit' => 'Árboles',
                'label' => 'Reforestación',
                'description' => 'Árboles nativos plantados en zonas ribereñas',
            ],
            [
                'icon' => 'water',
                'value' => '40%',
                'unit' => 'Mejora',
                'label' => 'Calidad del Agua',
                'description' => 'Reducción de contaminantes principales',
            ],
            [
                'icon' => 'fish',
                'value' => '25',
                'unit' => 'Especies',
                'label' => 'Biodiversidad',
                'description' => 'Especies reintroducidas exitosamente',
            ],
            [
                'icon' => 'users',
                'value' => '2,500',
                'unit' => 'Personas',
                'label' => 'Voluntarios',
                'description' => 'Participantes en actividades de conservación',
            ],
            [
                'icon' => 'award',
                'value' => '80%',
                'unit' => 'Avance',
                'label' => 'Área Restaurada',
                'description' => 'Zona de la laguna recuperada',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.about.projects-section');
    }
}
