<?php

namespace App\Livewire\News;

use App\DTOs\NewsCardDto;
use App\Services\NewsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: listado paginado de noticias con búsqueda y filtro.
 *
 * RESPONSABILIDADES:
 *  - Gestionar el estado de búsqueda y filtro de categoría.
 *  - Delegar toda obtención de datos al NewsService.
 *  - Pasar DTOs a la vista — sin lógica de negocio ni acceso a BD.
 *
 * SEGURIDAD:
 *  - #[Url] expone search y category en la URL para compartir links,
 *    pero ambos se sanitizan en el repositorio antes de usarse en queries.
 *  - Rate limiting en updatingSearch() → previene fuerza bruta / abuso.
 *  - Nunca se expone el ID entero; el UUID es el único identificador público.
 *  - Manejo de excepciones: errores de BD no llegan a la vista.
 */
class NewsIndex extends Component
{
    use WithPagination;

    // ── Estado reactivo (sincronizado con URL query string) ──────────────
    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $category = 'all';

    // ── Constantes de paginación ─────────────────────────────────────────
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

        $key = 'news-search:'.request()->fingerprint();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            $this->search = '';

            return;
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    public function updatingCategory($value): void
    {
        $this->category = mb_substr($value, 0, 120);
        $this->resetPage();
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render(NewsService $service)
    {
        try {
            $featured = $service->getFeatured($this->search, $this->category);
            $newsPaginator = $service->getPaginatedRest(
                search: $this->search,
                categoryName: $this->category,
                excludeUuid: $featured?->uuid,
                perPage: self::PER_PAGE,
            );
            $totalResults = $service->countResults($this->search, $this->category);
            $categories = $service->getCategoryOptions();

        } catch (\Throwable) {
            // En caso de error de BD u otro fallo, mostramos estado vacío
            // sin revelar detalles internos al usuario.
            $featured = null;
            $newsPaginator = $this->emptyPaginator();
            $totalResults = 0;
            $categories = ['all' => 'Todas las categorías'];
        }

        return view('livewire.news.news-index', [
            'featured' => $featured,       // NewsCardDto|null
            'newsPaginator' => $newsPaginator,   // LengthAwarePaginator<NewsCardDto>
            'totalResults' => $totalResults,
            'categories' => $categories,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /**
     * Paginador vacío para el estado de error — evita condicionales en blade.
     */
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
