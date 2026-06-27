<?php

namespace App\Livewire\Admin\Widgets;

use App\Models\Event;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Widget de eventos del dashboard de administración.
 *
 * Muestra los 5 eventos publicados según el filtro seleccionado:
 *  - 'proximos'     → eventos futuros, ordenados por fecha de inicio (más próximo primero)
 *  - 'recientes'    → ordenados por fecha de creación (más nuevo primero)
 *  - 'mas-inscritos'→ ordenados por número de inscripciones descendente
 *
 * RENDIMIENTO:
 *  - withCount('registrations') agrega el conteo en una sola consulta SQL.
 *  - Solo se seleccionan columnas necesarias para la vista.
 *
 * SEGURIDAD:
 *  - $filtro validado contra lista blanca en updatingFiltro().
 *  - XSS prevenido por el escape automático de {{ }} en Blade.
 */
class EventsWidget extends Component
{
    private const FILTROS_VALIDOS = ['proximos', 'recientes', 'mas-inscritos'];

    // Filtro activo — se sincroniza con el <select> de la vista
    public string $filtro = 'proximos';

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN
    // ════════════════════════════════════════════════════════════════════

    /** Valida el nuevo valor del filtro antes de aplicarlo */
    public function updatingFiltro(string $valor): void
    {
        if (! in_array($valor, self::FILTROS_VALIDOS, strict: true)) {
            $this->filtro = 'proximos';
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.widgets.events-widget', [
            'eventos' => $this->obtenerEventos(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS DE CONSULTA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Obtiene 5 eventos publicados con conteo de inscripciones.
     * withCount genera un LEFT JOIN con subconsulta en vez de N+1 queries.
     * Carga la imagen de portada con eager loading para evitar N+1.
     */
    private function obtenerEventos(): Collection
    {
        return Event::published()
            ->withCount('registrations') // registrations_count disponible en la colección
            // Carga ansiosa de la primera imagen: una sola query extra para todos los eventos
            ->with(['media' => function ($query) {
                $query->where('mime', 'like', 'image/%')
                      ->orderBy('order')
                      ->limit(1);
            }])
            ->when(
                $this->filtro === 'proximos',
                fn ($q) => $q->upcoming()->orderBy('start_at')
            )
            ->when(
                $this->filtro === 'recientes',
                fn ($q) => $q->orderByDesc('created_at')
            )
            ->when(
                $this->filtro === 'mas-inscritos',
                fn ($q) => $q->orderByDesc('registrations_count')
            )
            ->take(5)
            // Se incluye 'id' porque Eloquent lo requiere para asociar relaciones cargadas
            ->get(['id', 'uuid', 'name', 'capacity_total', 'start_at', 'location']);
    }
}
