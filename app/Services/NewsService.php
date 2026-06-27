<?php

namespace App\Services;

use App\DTOs\NewsCardDto;
use App\Repositories\NewsRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;

/**
 * Servicio de noticias.
 *
 * RESPONSABILIDADES:
 *  - Orquestar llamadas al repositorio.
 *  - Transformar modelos Eloquent en DTOs listos para la vista.
 *  - Contener toda la lógica de negocio (cálculo de "destacado",
 *    construcción del listado de categorías con la opción "Todas").
 *  - Ser la única dependencia que los componentes Livewire necesitan inyectar.
 *
 * Los componentes Livewire no conocen ni el modelo News ni el repositorio;
 * solo trabajan con DTOs y datos primitivos.
 */
class NewsService
{
    public function __construct(
        private readonly NewsRepository $repository
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por NewsIndex
    // ════════════════════════════════════════════════════════════════════

    /**
     * Noticia destacada (la más reciente del conjunto filtrado) como DTO.
     * Retorna null si no hay noticias publicadas.
     */
    public function getFeatured(string $search, string $categoryName): ?NewsCardDto
    {
        $model = $this->repository->findFeatured($search, $categoryName);
        return $model ? NewsCardDto::fromModel($model) : null;
    }

    /**
     * Paginador de las noticias restantes (excluyendo la destacada),
     * con cada ítem ya transformado a NewsCardDto.
     *
     * @return LengthAwarePaginator<NewsCardDto>
     */
    public function getPaginatedRest(
        string  $search,
        string  $categoryName,
        ?string $excludeUuid,
        int     $perPage = 9
    ): LengthAwarePaginator {
        $paginator = $this->repository->paginateExcluding(
            $search,
            $categoryName,
            $excludeUuid,
            $perPage
        );

        // Transforma los modelos Eloquent en DTOs de forma lazy sobre el paginador
        return $paginator->through(
            fn ($news) => NewsCardDto::fromModel($news)
        );
    }

    /**
     * Cuenta total de resultados publicados con los filtros actuales.
     * Se usa para mostrar "N resultados" en el toolbar.
     */
    public function countResults(string $search, string $categoryName): int
    {
        return $this->repository->countPublished($search, $categoryName);
    }

    /**
     * Últimas N noticias publicadas para la sección de noticias del home.
     *
     * PROPÓSITO (principio DRY):
     *  Centraliza la obtención de noticias del home en la capa de servicio.
     *  Los componentes Livewire del home (NewsSection) solo necesitan
     *  inyectar NewsService — sin acceso directo a Eloquent ni al repositorio.
     *
     * @param  int        $limit  Máximo de noticias a retornar (default 3).
     * @return Collection<int, News>
     */
    public function getLatestForHome(int $limit = 3): Collection
    {
        // Delega al repositorio — única fuente de verdad para esta query.
        return $this->repository->latestForHome($limit);
    }

    /**
     * Opciones del selector de categorías para el toolbar.
     * Retorna un array asociativo [valor => etiqueta] listo para @foreach en blade.
     *
     * Solo incluye categorías con al menos una noticia publicada.
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(): array
    {
        $categories = $this->repository->availableCategories();

        return collect(['all' => 'Todas las categorías'])
            ->merge($categories->mapWithKeys(fn ($name) => [$name => $name]))
            ->toArray();
    }
}
