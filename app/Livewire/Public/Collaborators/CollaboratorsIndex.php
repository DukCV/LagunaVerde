<?php

namespace App\Livewire\Public\Collaborators;

use App\Services\PartnersService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: listado público de socios colaboradores (/colaboradores).
 *
 * RESPONSABILIDADES:
 *  - Gestionar el estado de búsqueda, categoría y orden.
 *  - Delegar toda obtención de datos a PartnersService.
 *  - Pasar DTOs a la vista — sin lógica de negocio ni acceso a BD.
 *
 * SEGURIDAD:
 *  - #[Url] expone search/type/sort en la URL para compartir enlaces;
 *    todos se sanitizan en el repositorio antes de usarse en queries.
 *  - Rate limiting en updatingSearch() → previene fuerza bruta / scraping.
 *  - La ruta /colaboradores ya aplica throttle:120,1 a nivel de servidor
 *    (ver routes/web.php) → defensa en capas junto con este límite por sesión.
 *  - openDetails() NO consulta la BD: busca el id dentro de la colección de
 *    la página actual ya cargada en memoria. Un id inexistente en esa
 *    página simplemente no abre el modal — cero costo adicional de BD.
 */
class CollaboratorsIndex extends Component
{
    use WithPagination;

    // ── Estado reactivo (sincronizado con URL query string) ──────────────
    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $type = 'todos';

    #[Url(history: true)]
    public string $sort = 'recientes';

    // ── Id del socio mostrado en el modal de detalles (null = cerrado) ───
    public ?int $selectedPartnerId = null;

    // ── Constantes de paginación y rate limiting ─────────────────────────
    private const PER_PAGE = 9;

    private const RATE_LIMIT_MAX = 20;   // máximo de actualizaciones de búsqueda

    private const RATE_LIMIT_DECAY = 60;   // por minuto

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS DE ACTUALIZACIÓN DE PROPIEDADES
    // ════════════════════════════════════════════════════════════════════

    /**
     * Se ejecuta antes de actualizar $search.
     *
     * SEGURIDAD:
     *  - Rate limiting por sesión: máximo RATE_LIMIT_MAX búsquedas por minuto.
     *    Previene abuso de la búsqueda como vector de scraping o DoS.
     *  - Si se excede el límite, se limpia el campo silenciosamente.
     */
    public function updatingSearch(string $value): void
    {
        $this->search = mb_substr($value, 0, 100);

        $key = 'collaborators-search:' . request()->fingerprint();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            $this->search = '';

            return;
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    public function updatingType(string $value): void
    {
        $this->type = mb_substr($value, 0, 30);
        $this->resetPage();
    }

    public function updatingSort(string $value): void
    {
        $this->sort = mb_substr($value, 0, 20);
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES — filtros y modal de detalles
    // ════════════════════════════════════════════════════════════════════

    /** Restablece búsqueda, categoría y orden a sus valores por defecto. */
    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'sort']);
        $this->resetPage();
    }

    /** Abre el modal de detalles del socio indicado (id ya presente en la página cargada). */
    public function openDetails(int $id): void
    {
        $this->selectedPartnerId = $id;
    }

    /** Cierra el modal de detalles. */
    public function closeDetails(): void
    {
        $this->selectedPartnerId = null;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(PartnersService $service)
    {
        try {
            $partnersPaginator = $service->getPaginatedPartners(
                search: $this->search,
                type: $this->type,
                sortBy: $this->sort,
                perPage: self::PER_PAGE,
            );
            $activeCount = $service->getActiveCount();
            $categories  = $service->getCategoryOptions();

        } catch (\Throwable) {
            // Error de BD → estado vacío sin exponer detalles internos al usuario.
            $partnersPaginator = $this->emptyPaginator();
            $activeCount = 0;
            $categories  = ['todos' => 'Todas las categorías'];
        }

        // Búsqueda en memoria dentro de la página actual — sin consulta adicional a la BD.
        $selectedPartner = $partnersPaginator->getCollection()
            ->first(fn ($partner) => $partner->id === $this->selectedPartnerId);

        return view('livewire.public.collaborators.collaborators-index', [
            'partnersPaginator' => $partnersPaginator, // LengthAwarePaginator<PartnerCardDto>
            'activeCount'       => $activeCount,
            'categories'        => $categories,
            'selectedPartner'   => $selectedPartner,   // PartnerCardDto|null
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /** Paginador vacío para el estado de error — evita condicionales adicionales en blade. */
    private function emptyPaginator(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator(
            items: [],
            total: 0,
            perPage: self::PER_PAGE,
            currentPage: 1,
            options: ['pageName' => 'page'],
        );
    }
}
