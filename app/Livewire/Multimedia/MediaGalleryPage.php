<?php

namespace App\Livewire\Multimedia;

use App\Services\Multimedia\MediaGalleryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: galería multimedia paginada con filtros combinables.
 *
 * RESPONSABILIDADES:
 *  - Gestionar el estado reactivo (búsqueda, filtro de tipo, paginación).
 *  - Delegar toda lógica de negocio y acceso a datos a MediaGalleryService.
 *  - Pasar AlbumDTOs a la vista — sin queries ni lógica de negocio aquí.
 *
 * La expansión de álbumes y el modal de media viven enteramente en Alpine.js
 * (ver resources/views/livewire/multimedia/media-gallery-page.blade.php):
 * son estado puramente de presentación sobre datos ya cargados en el DOM,
 * por lo que no requieren ida y vuelta al servidor — esto elimina el jank
 * de red en cada apertura/cierre y permite transiciones fluidas con
 * x-transition.
 *
 * SEGURIDAD:
 *  - #[Url] sincroniza filtros con la URL para links compartibles.
 *  - RateLimiter en updatingSearch() y updatingPaginators() → previene
 *    scraping y abuso tanto de la búsqueda como de la paginación.
 *  - Validación de $typeFilter contra whitelist antes de pasar al servicio.
 *  - try/catch en render() → errores de BD no llegan a la vista del usuario.
 *  - El UUID se usa como identificador público; el ID entero nunca aparece.
 */
class MediaGalleryPage extends Component
{
    use WithPagination;

    // ── Estado reactivo sincronizado con URL ─────────────────────────────

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $typeFilter = 'all';   // 'all' | 'image' | 'video'

    // ── Configuración ─────────────────────────────────────────────────────

    private const PER_PAGE         = 9;
    private const RATE_LIMIT_MAX   = 20;   // acciones máximas por minuto
    private const RATE_LIMIT_DECAY = 60;   // segundos de ventana del rate limiter

    // ── Whitelist de valores válidos para el filtro de tipo ───────────────
    private const TYPE_FILTER_WHITELIST = ['all', 'image', 'video'];

    // ════════════════════════════════════════════════════════════════════
    //  HOOKS — reset de paginación al cambiar filtros + rate limiting
    // ════════════════════════════════════════════════════════════════════

    /**
     * Hook ejecutado antes de actualizar $search.
     * Aplica rate limiting para prevenir abuso y resetea la paginación.
     */
    public function updatingSearch(): void
    {
        // Identificar sesión para el rate limiter (sin exponer datos de usuario)
        $key = 'gallery-search:' . session()->getId();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            // Si se excede el límite, restablecer la búsqueda para evitar spam
            $this->search = '';
            return;
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
        $this->resetPage();
    }

    /** Resetear página al cambiar el filtro de tipo */
    public function updatingTypeFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Hook nativo de Livewire\WithPagination, invocado antes de cambiar de
     * página (gotoPage/nextPage/previousPage). Aplica el mismo rate limiting
     * que la búsqueda para mitigar paginación automatizada (scraping/DoS
     * mediante peticiones masivas de "cargar más media").
     */
    public function updatingPaginators(int|string $page, ?string $pageName = null): void
    {
        $key = 'gallery-page:' . session()->getId();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            return;
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    /**
     * Renderiza el componente inyectando el servicio de galería.
     *
     * La inyección por método (render injection) permite que el
     * contenedor de IoC resuelva el servicio automáticamente.
     */
    public function render(MediaGalleryService $service): \Illuminate\View\View
    {
        try {
            // Validar el filtro de tipo contra la whitelist antes de consultar
            $safeTypeFilter = in_array($this->typeFilter, self::TYPE_FILTER_WHITELIST, true)
                ? $this->typeFilter
                : 'all';

            $albums      = $service->getAlbums(
                search:     $this->search,
                typeFilter: $safeTypeFilter,
                perPage:    self::PER_PAGE,
                page:       $this->getPage(),
            );
            $typeOptions = $service->getTypeFilterOptions();

        } catch (\Throwable) {
            // Error de BD → estado vacío sin exponer detalles al usuario
            $albums      = $this->emptyPaginator();
            $typeOptions = ['all' => 'Todos', 'image' => 'Imágenes', 'video' => 'Videos'];
        }

        return view('livewire.multimedia.media-gallery-page', [
            'albums'        => $albums,       // LengthAwarePaginator<AlbumDto>
            'typeOptions'   => $typeOptions,
            'totalResults'  => $albums->total(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPER PRIVADO
    // ════════════════════════════════════════════════════════════════════

    /** Paginador vacío para el estado de error — evita nulos en la vista */
    private function emptyPaginator(): LengthAwarePaginator
    {
        return new ConcretePaginator(
            items:       [],
            total:       0,
            perPage:     self::PER_PAGE,
            currentPage: 1,
            options:     ['pageName' => 'page'],
        );
    }
}
