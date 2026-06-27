<?php

namespace App\Livewire\Admin\Widgets;

use App\Models\PageVisit;
use Carbon\CarbonImmutable;
use Livewire\Component;

/**
 * Widget de gráfico de tráfico del sitio.
 *
 * Genera datos reales de visitas desde la tabla page_visits agrupados por:
 *  - 'dia'    → tramos de 4 horas (últimas 24 horas vs ayer)
 *  - 'semana' → días de la semana (últimos 7 días vs semana previa)
 *  - 'anio'   → meses del año (últimos 12 meses)
 *
 * El frontend (Alpine.js + Chart.js) consume los datos y renderiza el área chart.
 * El wire:key en la vista fuerza la reinicialización de Chart.js cuando cambian
 * los filtros — evita bugs de estado stale en el canvas.
 *
 * RENDIMIENTO:
 *  - Máximo 2 consultas de agregación por render (actuales + previas).
 *  - Usa selectRaw con GROUP BY para evitar N+1 de fechas.
 *  - El índice en visited_at garantiza un range scan eficiente.
 */
class TrafficChart extends Component
{
    private const RANGOS_VALIDOS = ['dia', 'semana', 'anio'];

    private const DIAS_ES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    private const MESES_ES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    // ── Estado reactivo ──────────────────────────────────────────────────
    public string $rango   = 'semana';
    public bool   $comparar = false;

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PÚBLICOS
    // ════════════════════════════════════════════════════════════════════

    /** Cambia el rango temporal del gráfico (validado contra lista blanca) */
    public function cambiarRango(string $rango): void
    {
        if (! in_array($rango, self::RANGOS_VALIDOS, strict: true)) {
            return;
        }

        $this->rango = $rango;
    }

    /** Alterna la comparación con el período anterior */
    public function toggleComparar(): void
    {
        $this->comparar = ! $this->comparar;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.widgets.traffic-chart', [
            'datos' => $this->obtenerDatos(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS DE CONSULTA
    // ════════════════════════════════════════════════════════════════════

    /** Despacha la generación de datos según el rango activo */
    private function obtenerDatos(): array
    {
        return match($this->rango) {
            'dia'    => $this->datosDiarios(),
            'semana' => $this->datosSemana(),
            'anio'   => $this->datosAnuales(),
        };
    }

    /**
     * Datos diarios: últimas 24 horas agrupadas en 6 tramos de 4 horas.
     * Compara contra las mismas horas de ayer si $comparar está activo.
     */
    private function datosDiarios(): array
    {
        $inicioHoy  = CarbonImmutable::now()->startOfDay();
        $inicioAyer = $inicioHoy->subDay();

        // Una sola consulta de agregación para hoy (range scan sobre visited_at)
        $visitasHoy = PageVisit::selectRaw('FLOOR(HOUR(visited_at)/4)*4 AS tramo, COUNT(*) AS total')
            ->where('visited_at', '>=', $inicioHoy)
            ->groupBy('tramo')
            ->pluck('total', 'tramo');

        // Una sola consulta para ayer (solo si se necesita la comparación)
        $visitasAyer = $this->comparar
            ? PageVisit::selectRaw('FLOOR(HOUR(visited_at)/4)*4 AS tramo, COUNT(*) AS total')
                ->whereBetween('visited_at', [$inicioAyer, $inicioHoy->subSecond()])
                ->groupBy('tramo')
                ->pluck('total', 'tramo')
            : collect();

        $labels  = [];
        $visitas = [];
        $previas = [];

        foreach ([0, 4, 8, 12, 16, 20] as $tramo) {
            $labels[]  = sprintf('%02d:00', $tramo);
            $visitas[] = (int) $visitasHoy->get($tramo, 0);
            $previas[] = (int) $visitasAyer->get($tramo, 0);
        }

        return compact('labels', 'visitas', 'previas');
    }

    /**
     * Datos semanales: últimos 7 días.
     * Compara contra los mismos 7 días de la semana anterior.
     */
    private function datosSemana(): array
    {
        $hoy          = CarbonImmutable::now();
        $inicio7Dias  = $hoy->subDays(6)->startOfDay();
        $inicioPrevio = $inicio7Dias->subDays(7);
        $finPrevio    = $inicio7Dias->subSecond();

        // Consulta de la semana actual con GROUP BY fecha
        $visitasActuales = PageVisit::selectRaw('DATE(visited_at) AS fecha, COUNT(*) AS total')
            ->where('visited_at', '>=', $inicio7Dias)
            ->groupBy('fecha')
            ->pluck('total', 'fecha');

        // Consulta de la semana previa solo si se muestra la comparación
        $visitasPrevias = $this->comparar
            ? PageVisit::selectRaw('DATE(visited_at) AS fecha, COUNT(*) AS total')
                ->whereBetween('visited_at', [$inicioPrevio, $finPrevio])
                ->groupBy('fecha')
                ->pluck('total', 'fecha')
            : collect();

        $labels  = [];
        $visitas = [];
        $previas = [];

        // Iterar de más antiguo a más reciente (i=6 → i=0)
        for ($i = 6; $i >= 0; $i--) {
            $fecha     = $hoy->subDays($i);
            $fechaPrev = $fecha->subDays(7);

            $labels[]  = self::DIAS_ES[$fecha->dayOfWeek];
            $visitas[] = (int) $visitasActuales->get($fecha->format('Y-m-d'), 0);
            $previas[] = (int) $visitasPrevias->get($fechaPrev->format('Y-m-d'), 0);
        }

        return compact('labels', 'visitas', 'previas');
    }

    /**
     * Datos anuales: últimos 12 meses agrupados por mes.
     * La comparación con el año anterior no se implementa en este rango.
     */
    private function datosAnuales(): array
    {
        $hoy            = CarbonImmutable::now();
        $inicio12Meses  = $hoy->subMonths(11)->startOfMonth();

        // Una consulta con GROUP BY año y mes para todos los 12 meses
        $visitasAnuales = PageVisit::selectRaw(
            'YEAR(visited_at) AS anio, MONTH(visited_at) AS mes, COUNT(*) AS total'
        )
            ->where('visited_at', '>=', $inicio12Meses)
            ->groupBy('anio', 'mes')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->anio}-{$row->mes}" => (int) $row->total]);

        $labels  = [];
        $visitas = [];
        $previas = [];

        for ($i = 11; $i >= 0; $i--) {
            $fecha = $hoy->subMonths($i);
            $clave = "{$fecha->year}-{$fecha->month}";

            $labels[]  = self::MESES_ES[$fecha->month - 1];
            $visitas[] = $visitasAnuales->get($clave, 0);
            $previas[] = 0; // Sin comparación anual en esta versión
        }

        return compact('labels', 'visitas', 'previas');
    }
}
