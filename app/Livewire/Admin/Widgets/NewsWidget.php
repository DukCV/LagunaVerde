<?php

namespace App\Livewire\Admin\Widgets;

use App\Models\News;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Widget de noticias recientes del dashboard de administración.
 *
 * Muestra las 5 últimas noticias publicadas con opciones de filtrado:
 *  - 'recientes'   → ordenadas por fecha de publicación (más nueva primero)
 *  - 'mas-vistas'  → ordenadas por views_count descendente
 *  - 'menos-vistas'→ ordenadas por views_count ascendente
 *
 * RENDIMIENTO:
 *  - Solo selecciona las columnas necesarias (sin content ni summary).
 *  - withCount('comments') genera un LEFT JOIN con COUNT en vez de N+1.
 *  - Índices en published_at y views_count garantizan ORDER BY eficiente.
 *
 * SEGURIDAD:
 *  - $filtro validado contra lista blanca antes de usarse en la query.
 *  - Toda salida en la vista usa {{ }} para escape XSS automático.
 */
class NewsWidget extends Component
{
    private const FILTROS_VALIDOS = ['recientes', 'mas-vistas', 'menos-vistas'];

    // Filtro activo — se sincroniza con el <select> de la vista
    public string $filtro = 'recientes';

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN
    // ════════════════════════════════════════════════════════════════════

    /** Valida el nuevo valor del filtro antes de aplicarlo */
    public function updatingFiltro(string $valor): void
    {
        if (! in_array($valor, self::FILTROS_VALIDOS, strict: true)) {
            $this->filtro = 'recientes'; // Fallback seguro
        }
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.widgets.news-widget', [
            'noticias' => $this->obtenerNoticias(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS DE CONSULTA
    // ════════════════════════════════════════════════════════════════════

    /**
     * Obtiene las 5 noticias publicadas aplicando el filtro de ordenación.
     * Selecciona solo las columnas que necesita la vista (evita SELECT *).
     * Carga la imagen de portada con eager loading para evitar N+1.
     */
    private function obtenerNoticias(): Collection
    {
        return News::published()
            ->withCount('comments') // LEFT JOIN con COUNT — evita N+1
            // Carga ansiosa de la primera imagen: una sola query extra para todas las noticias
            ->with(['media' => function ($query) {
                $query->where('mime', 'like', 'image/%')
                      ->orderBy('order')
                      ->limit(1);
            }])
            ->when(
                $this->filtro === 'recientes',
                fn ($q) => $q->orderByDesc('published_at')
            )
            ->when(
                $this->filtro === 'mas-vistas',
                fn ($q) => $q->orderByDesc('views_count')
            )
            ->when(
                $this->filtro === 'menos-vistas',
                fn ($q) => $q->orderBy('views_count')
            )
            ->take(5)
            // Se incluye 'id' porque Eloquent lo requiere para asociar relaciones cargadas
            ->get(['id', 'uuid', 'title', 'views_count', 'published_at']);
    }
}
