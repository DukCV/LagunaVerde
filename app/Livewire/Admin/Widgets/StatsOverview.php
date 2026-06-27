<?php

namespace App\Livewire\Admin\Widgets;

use App\Models\PageVisit;
use App\Models\User;
use Livewire\Component;

/**
 * Widget de estadísticas globales del dashboard.
 *
 * Muestra tres métricas en tiempo real:
 *  1. Total de visitas al sitio público (tabla page_visits)
 *  2. Total de usuarios registrados
 *  3. Total de colaboradores (usuarios con rol 'Colaborador')
 *
 * ACTUALIZACIÓN: wire:poll.30s refresca las métricas cada 30 segundos
 * sin intervención del usuario — ideal para un panel de control activo.
 *
 * RENDIMIENTO:
 *  - Cada conteo usa COUNT(*) con índice primario → O(1) en InnoDB.
 *  - whereHas con subquery indexada por 'name' en la tabla roles.
 */
class StatsOverview extends Component
{
    // Nombre del rol de colaborador — centralizado para evitar magic strings
    private const ROL_COLABORADOR = 'Colaborador';

    public function render()
    {
        return view('livewire.admin.widgets.stats-overview', [
            'totalVisitas'       => $this->contarVisitas(),
            'totalUsuarios'      => User::count(),
            'totalColaboradores' => $this->contarColaboradores(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS DE CONSULTA
    // ════════════════════════════════════════════════════════════════════

    /** Cuenta el total de visitas registradas en el sitio público */
    private function contarVisitas(): int
    {
        return PageVisit::count();
    }

    /**
     * Cuenta usuarios con el rol 'Colaborador'.
     * Usa whereHas para generar un EXISTS subquery eficiente con índice en roles.name.
     */
    private function contarColaboradores(): int
    {
        return User::whereHas(
            'roles',
            fn ($q) => $q->where('name', self::ROL_COLABORADOR)
        )->count();
    }
}
