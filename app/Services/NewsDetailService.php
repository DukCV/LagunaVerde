<?php

namespace App\Services;

use App\Concerns\ValidatesUuid;
use App\DTOs\NewsDetailDto;
use App\DTOs\SidebarNewsDto;
use App\Repositories\NewsDetailRepository;

/**
 * Servicio para la página de detalle de noticias.
 *
 * RESPONSABILIDADES:
 *  - Validar el UUID antes de consultar la BD.
 *  - Orquestar llamadas al repositorio.
 *  - Transformar modelos en DTOs.
 *  - Ser la única dependencia que los componentes Livewire inyectan.
 *
 * SEGURIDAD:
 *  - Validación de UUID con regex: rechaza cualquier input malformado
 *    antes de que llegue a la BD.
 *  - null silencioso ante UUID inválido o no publicado → sin distinción
 *    que permita enumerar recursos.
 */
class NewsDetailService
{
    use ValidatesUuid;

    public function __construct(
        private readonly NewsDetailRepository $repository
    ) {}

    /**
     * Retorna el DTO completo de la noticia o null si no es accesible.
     * El componente debe llamar abort(404) si recibe null.
     */
    public function getDetail(string $uuid): ?NewsDetailDto
    {
        if (! $this->isValidUuid($uuid)) {
            return null;
        }

        $news = $this->repository->findPublishedByUuid($uuid);

        return $news ? NewsDetailDto::fromModel($news) : null;
    }

    /**
     * Últimas 3 noticias publicadas para el sidebar (excluye la actual).
     *
     * @return SidebarNewsDto[]
     */
    public function getLatestForSidebar(string $currentUuid, int $limit = 3): array
    {
        if (! $this->isValidUuid($currentUuid)) {
            return [];
        }

        return $this->repository
            ->latestForSidebar($currentUuid, $limit)
            ->map(fn ($n) => SidebarNewsDto::fromModel($n))
            ->toArray();
    }
}
