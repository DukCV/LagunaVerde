<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SERVICIO: Estadísticas de impacto público (Home + Quiénes Somos)
//
//  Una sola entrada de caché para los 2 conteos — evita lecturas de caché
//  separadas en cada wire:poll. El tercer dato ("100% Comprometidos") es
//  fijo y vive directamente en la vista — ver impact-stats.blade.php.
//
//  Seguridad/Rendimiento (Hostinger):
//    - Cache::remember() con TTL corto: el wire:poll de la vista nunca
//      golpea la BD directamente; como máximo recalcula 1 vez por TTL,
//      sin importar cuántas pestañas/usuarios estén viendo la página.
//    - Solo count() — nunca se cargan filas completas en memoria.
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Services\Stats;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ImpactStatsService
{
    private const CACHE_KEY = 'estadisticas_impacto';
    private const CACHE_TTL_SEGUNDOS = 60;

    /**
     * @return array{voluntariosActivos: int, colaboradores: int}
     */
    public function obtenerEstadisticas(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds(self::CACHE_TTL_SEGUNDOS),
            fn () => [
                'voluntariosActivos' => User::where('active', true)->count(),
                // Todos los socios, vinculados o no a un usuario (sin filtro de estado)
                'colaboradores'      => Partner::count(),
            ],
        );
    }
}
