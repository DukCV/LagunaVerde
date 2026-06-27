<?php

namespace App\Livewire\About;

use Livewire\Component;

class AboutHero extends Component
{
    public array $timeline = [];
    public bool $showAll = false;
    public int $initialCount = 5;

    public function mount()
    {
        $this->timeline = [
            [
                'icon'  => '📋',
                'label' => 'Sesiones',
                'event' => '56 sesiones semanales con quórum realizadas durante el período 2023-2024.',
            ],
            [
                'icon'  => '🤝',
                'label' => 'Mesas de Trabajo',
                'event' => '02 mesas de trabajo: 15 de enero con la Dra. Marisol Gallardo; 05 de Abril 2024 sobre Sustentabilidad de la Laguna impartida por Mtra. Alma Ríos Flores.',
            ],
            [
                'icon'  => '🌿',
                'label' => 'Visitas al Humedal',
                'event' => '02 visitas al Humedal de Valle de Bravo, Estado de México, como caso práctico de solución al saneamiento de aguas residuales, con participación del ayuntamiento, regidora de Ecología, SOSAPACH, colectivo Tejiendo Memorias, Colectivo Ixtlahuaca, CIITAS y el Comité por la Sustentabilidad.',
            ],
            [
                'icon'  => '🎨',
                'label' => 'Concurso de Cartel',
                'event' => '1 concurso de Cartel "Laguna Chignahuapan" a nivel preparatoria.',
            ],
            [
                'icon'  => '🌳',
                'label' => 'Jornadas de Reforestación',
                'event' => '02 jornadas de reforestación en la periferia del segundo vaso de la Laguna, sumando 70 árboles sauce-llorón y encino. Una de ellas con mensaje de movilidad en bicicleta, con participación de vecinos y escuelas (Sor Juana Inés de la Cruz, Preparatoria IINDEXA).',
            ],
            [
                'icon'  => '🗣️',
                'label' => 'Escuchas Ciudadanas',
                'event' => '02 escuchas ciudadanas con vecinos de Ixtlahuaca, Teoconchila, miembros de Tomatlán, presidentes de comités de agua potable y operadores de pipas.',
            ],
            [
                'icon'  => '🎙️',
                'label' => 'Podcasts',
                'event' => '03 transmisiones de Podcast en cabina de AX-RADIO y 02 transmisiones de Podcast en el kiosco de Chignahuapan.',
            ],
            [
                'icon'  => '🏫',
                'label' => 'Visita Educativa',
                'event' => 'Visita al Colegio Sor Juana Inés de la Cruz con el tema "CUIDA EL AGUA".',
            ],
            [
                'icon'  => '♻️',
                'label' => 'Faena de Lirio',
                'event' => 'Apoyo en una faena de control de lirio y recolección de basura en la zona del vivero.',
            ],
            [
                'icon'  => '🌾',
                'label' => 'Programa Sembrando Vida',
                'event' => 'Vinculación y gestión del programa Federal SEMBRANDO VIDA con 800 ejidatarios en el Cholón para control de lirio, cumpliendo labor social mensual.',
            ],
            [
                'icon'  => '🏞️',
                'label' => 'Vinculación Turística',
                'event' => 'Acercamiento con Cluster Experiencias Turísticas Chignahuapan-Zacatlán A. C.',
            ],
            [
                'icon'  => '🔬',
                'label' => 'Investigaciones',
                'event' => '03 investigaciones en proceso: Plantas invasoras (UIEPA); Estudios Genotóxicos (UPAEP) con conferencia del Dr. Luis Daniel Ortega Martínez el 01 de Julio en la BUAP; Caso de estudio de la microcuenca río Chignahuapan (Tecnológico de Zacatlán).',
            ],
            [
                'icon'  => '🏛️',
                'label' => '1er Congreso de la Laguna',
                'event' => 'Organización del Primer Congreso para el Cuidado, Conservación y Preservación de la Laguna Chignahuapan, con 07 universidades, 02 A.C., 02 actores políticos, asistencia de la Diputada Katya Sánchez Rodríguez, 180 asistentes presenciales y 4,204 en modo virtual.',
            ],
            [
                'icon'  => '🎬',
                'label' => 'Documental',
                'event' => 'Realización del documental "MEMORIAS DEL 1ER CONGRESO PARA EL CUIDADO, CONSERVACIÓN Y PRESERVACIÓN DE LA LAGUNA CHIGNAHUAPAN".',
            ],
        ];
    }

    public function toggleShowAll(): void
    {
        $this->showAll = ! $this->showAll;
    }

    public function render()
    {
        return view('livewire.about.about-hero');
    }
}
