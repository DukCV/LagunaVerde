<?php

namespace App\Services\Events;

use App\DTOs\Events\EventSummaryDto;
use App\DTOs\Events\FeaturedEventDto;
use App\Repositories\Events\EventIndexRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Servicio para el índice de eventos.
 *
 * RESPONSABILIDADES:
 *  - Validar y sanitizar los filtros antes de pasarlos al repositorio.
 *  - Construir las opciones de filtros (categorías, meses) con valores seguros.
 *  - Transformar modelos Eloquent en DTOs listos para la vista.
 *  - Ser la única dependencia que EventsIndex inyecta.
 *
 * Los componentes Livewire no conocen ni los modelos Eloquent
 * ni el repositorio — solo trabajan con DTOs y datos primitivos.
 */
class EventIndexService
{
    // Valores válidos para el filtro de estado — whitelist explícita
    private const VALID_STATUSES = ['all', 'active', 'finished'];

    public function __construct(
        private readonly EventIndexRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por EventsIndex
    // ════════════════════════════════════════════════════════════════════

    /**
     * Evento más próximo para el banner destacado o null si no existe.
     */
    public function getFeatured(): ?FeaturedEventDto
    {
        $event = $this->repository->findFeatured();
        return $event ? FeaturedEventDto::fromModel($event) : null;
    }

    /**
     * Paginador de eventos con filtros, cada ítem transformado a EventSummaryDto.
     *
     * @return LengthAwarePaginator<EventSummaryDto>
     */
    public function getPaginated(
        string $search,
        string $category,
        string $monthYear,
        string $status,
        int    $perPage = 9
    ): LengthAwarePaginator {
        // Validar el filtro de estado contra whitelist
        $safeStatus = in_array($status, self::VALID_STATUSES, strict: true)
            ? $status
            : 'all';

        // Validar monthYear: debe ser 'YYYY-MM' o vacío
        $safeMonth = preg_match('/^\d{4}-\d{2}$/', $monthYear) ? $monthYear : '';

        $paginator = $this->repository->paginate(
            search:       $search,
            categoryName: $category === 'Todas' ? '' : $category,
            monthYear:    $safeMonth,
            status:       $safeStatus,
            perPage:      $perPage,
        );

        // Transforma los modelos en DTOs de forma lazy sobre el paginador
        return $paginator->through(
            fn ($event) => EventSummaryDto::fromModel($event)
        );
    }

    /**
     * Opciones para el select de categorías.
     * Retorna array ['Todas', 'Taller', 'Conferencia', ...] desde BD real.
     *
     * @return string[]
     */
    public function getCategoryOptions(): array
    {
        return array_merge(
            ['Todas'],
            $this->repository->availableCategories()
        );
    }

    /**
     * Opciones para el select de meses.
     * Retorna array de ['value' => 'YYYY-MM', 'label' => 'Julio 2025'].
     * Incluye opción "Todos los meses" al inicio.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function getMonthOptions(): array
    {
        return array_merge(
            [['value' => '', 'label' => 'Todos los meses']],
            $this->repository->availableMonths()
        );
    }

    /**
     * Opciones fijas para el filtro de estado.
     * No se leen de BD porque son valores del enum de la aplicación.
     *
     * @return array<string, string>  [value => label]
     */
    public function getStatusOptions(): array
    {
        return [
            'all'      => 'Todos',
            'active'   => 'Próximos',
            'finished' => 'Finalizados',
        ];
    }
}
