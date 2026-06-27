<?php

namespace App\Livewire\Shared;

use App\Services\Stats\ImpactStatsService;
use Livewire\Component;

/**
 * Componente reutilizable: estadísticas de impacto en tiempo real.
 * Usado en Home (hero) y Quiénes Somos (CTA) — una sola fuente de verdad.
 * El tercer dato ("100% Comprometidos") es fijo — ver impact-stats.blade.php.
 *
 * wire:poll en la vista refresca actualizar() periódicamente; el costo
 * real de BD está acotado por Cache::remember() en ImpactStatsService,
 * no por la frecuencia del poll.
 */
class ImpactStats extends Component
{
    public int $voluntariosActivos = 0;
    public int $colaboradores      = 0;

    public function mount(ImpactStatsService $service): void
    {
        $this->actualizar($service);
    }

    /** Refresca los datos — invocado por wire:poll */
    public function actualizar(ImpactStatsService $service): void
    {
        $datos = $service->obtenerEstadisticas();

        $this->voluntariosActivos = $datos['voluntariosActivos'];
        $this->colaboradores      = $datos['colaboradores'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.shared.impact-stats');
    }
}
