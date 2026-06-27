<?php

namespace App\Livewire\Events;

use App\DTOs\Events\FeaturedEventDto;
use App\Services\Events\EventIndexService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: listado paginado de eventos con filtros combinables.
 *
 * RESPONSABILIDADES:
 *  - Gestionar el estado reactivo de filtros (search, category, month, status).
 *  - Delegar toda lógica de negocio y acceso a datos a EventIndexService.
 *  - Pasar DTOs a la vista — sin queries ni lógica de negocio aquí.
 *
 * SEGURIDAD:
 *  - #[Url] sincroniza filtros con la URL (shareable links).
 *  - RateLimiter en updatingSearch() — previene abuso de búsqueda.
 *  - Validación de filtro 'status' contra whitelist en el servicio.
 *  - try/catch en render() — errores de BD no llegan a la vista.
 *  - UUID en enlaces — el ID entero nunca aparece en el HTML.
 */
class EventsIndex extends Component
{
    use WithPagination;

    // ── Estado reactivo sincronizado con URL ─────────────────────────────
    #[Url(history: true)]
    public string $search   = '';

    #[Url(history: true)]
    public string $category = 'Todas';

    #[Url(history: true)]
    public string $month    = '';           // formato "YYYY-MM" o vacío

    #[Url(history: true)]
    public string $status   = 'all';       // 'all' | 'active' | 'finished'

    // ── Configuración ────────────────────────────────────────────────────
    private const PER_PAGE         = 9;
    private const RATE_LIMIT_MAX   = 20;   // búsquedas máximas por minuto
    private const RATE_LIMIT_DECAY = 60;

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS — reset de paginación al cambiar filtros
    // ════════════════════════════════════════════════════════════════════

    /**
     * Rate limiting en búsqueda — previene scraping y abuso.
     */
    public function updatingSearch(): void
    {
        $key = 'events-search:' . session()->getId();
        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            $this->search = '';
            return;
        }
        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    public function updatingCategory(): void { $this->resetPage(); }
    public function updatingMonth(): void    { $this->resetPage(); }
    public function updatingStatus(): void   { $this->resetPage(); }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(EventIndexService $service)
    {
        try {
            $featured   = $service->getFeatured();
            $paginator  = $service->getPaginated(
                search:    $this->search,
                category:  $this->category,
                monthYear: $this->month,
                status:    $this->status,
                perPage:   self::PER_PAGE,
            );
            $categories = $service->getCategoryOptions();
            $months     = $service->getMonthOptions();
            $statuses   = $service->getStatusOptions();

        } catch (\Throwable) {
            // Error de BD → estado vacío sin exponer detalles al usuario
            $featured   = null;
            $paginator  = $this->emptyPaginator();
            $categories = ['Todas'];
            $months     = [['value' => '', 'label' => 'Todos los meses']];
            $statuses   = ['all' => 'Todos'];
        }

        return view('livewire.events.events-index', [
            'featuredEvent'   => $featured instanceof FeaturedEventDto
                                    ? $featured->toArray()
                                    : null,
            'eventsPaginator' => $paginator,   // LengthAwarePaginator<EventSummaryDto>
            'categories'      => $categories,
            'months'          => $months,
            'statuses'        => $statuses,
            'totalResults'    => $paginator->total(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /** Paginador vacío para el estado de error — evita nulos en la vista. */
    private function emptyPaginator(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            items:       [],
            total:       0,
            perPage:     self::PER_PAGE,
            currentPage: 1,
            options:     ['pageName' => 'page'],
        );
    }
}
