<?php

namespace App\Services\Multimedia;

use App\DTOs\Multimedia\AlbumDto;
use App\Repositories\Multimedia\MediaGalleryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Servicio de la galería multimedia.
 *
 * RESPONSABILIDADES:
 *  - Orquestar las llamadas al repositorio.
 *  - Transformar los modelos Eloquent en AlbumDTOs listos para la vista.
 *  - Ser la única dependencia que MediaGalleryPage necesita inyectar.
 *
 * El componente Livewire no conoce ni los modelos News/Event ni el
 * repositorio; sólo trabaja con AlbumDto y datos primitivos.
 */
class MediaGalleryService
{
    public function __construct(
        private readonly MediaGalleryRepository $repository,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  API PÚBLICA — usada por MediaGalleryPage
    // ════════════════════════════════════════════════════════════════════

    /**
     * Devuelve un paginador de AlbumDTOs aplicando los filtros actuales.
     *
     * La transformación model → DTO se realiza con paginador::through()
     * de forma lazy para no iterar dos veces la colección.
     *
     * @param  string  $search      Término de búsqueda libre.
     * @param  string  $typeFilter  'all' | 'image' | 'video'
     * @param  int     $perPage     Álbumes por página.
     * @param  int     $page        Número de página actual.
     * @return LengthAwarePaginator<AlbumDto>
     */
    public function getAlbums(
        string $search,
        string $typeFilter,
        int    $perPage = 9,
        int    $page    = 1,
    ): LengthAwarePaginator {
        $paginator = $this->repository->getAlbums(
            search:     $search,
            typeFilter: $typeFilter,
            perPage:    $perPage,
            page:       $page,
        );

        // Transformar cada modelo Eloquent en un AlbumDto inmutable
        return $paginator->through(
            fn ($model) => AlbumDto::fromModel($model)
        );
    }

    /**
     * Opciones del filtro de tipo de media para la barra de filtros.
     *
     * @return array<string, string>  ['value' => 'Etiqueta']
     */
    public function getTypeFilterOptions(): array
    {
        return [
            'all'   => 'Todos',
            'image' => 'Imágenes',
            'video' => 'Videos',
        ];
    }
}
