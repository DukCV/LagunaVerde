<?php

namespace App\Services;

use App\DTOs\PartnerCardDto;
use App\Models\Partner;
use App\Repositories\PartnersRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Servicio público de socios colaboradores.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas a PartnersRepository.
 *  - Transformar modelos Eloquent en PartnerCardDto.
 *  - Construir las opciones del selector de categoría.
 *
 * El componente Livewire no conoce ni el modelo Partner ni el repositorio;
 * solo trabaja con DTOs y arrays primitivos (mismo patrón que NewsService).
 */
class PartnersService
{
    public function __construct(
        private readonly PartnersRepository $repository
    ) {}

    /**
     * Paginador de socios activos con cada ítem transformado a PartnerCardDto.
     *
     * @return LengthAwarePaginator<PartnerCardDto>
     */
    public function getPaginatedPartners(string $search, string $type, string $sortBy, int $perPage = 9): LengthAwarePaginator
    {
        $paginator = $this->repository->paginate($search, $type, $sortBy, $perPage);

        return $paginator->through(
            fn (Partner $partner) => PartnerCardDto::fromModel($partner)
        );
    }

    /** Total de socios activos — alimenta el contador del banner. */
    public function getActiveCount(): int
    {
        return $this->repository->countActive();
    }

    /**
     * Opciones del selector de categoría [valor => etiqueta], incluida "Todas".
     * Partner::TYPES es una lista fija y pequeña — no requiere consultar la BD.
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        return collect(['todos' => 'Todas las categorías'])
            ->merge(collect(Partner::TYPES)->mapWithKeys(fn ($type) => [$type => $type]))
            ->toArray();
    }
}
