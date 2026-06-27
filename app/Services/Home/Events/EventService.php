<?php

namespace App\Services\Home\Events;

use App\DTOs\Home\Events\EventCardDto;
use App\Repositories\Home\Events\EventRepository;

/**
 * Servicio de eventos para la sección Home.
 *
 * Namespace alineado con la ruta real del archivo:
 *   app/Services/Home/Events/EventService.php
 */
class EventService
{
    public function __construct(
        private readonly EventRepository $repository
    ) {}

    /**
     * Próximos eventos publicados transformados en DTOs.
     *
     * @param  int $max  Máximo de eventos (default 3).
     * @return EventCardDto[]
     */
    public function getUpcoming(int $max = 3): array
    {
        return $this->repository
            ->nextPublished($max)
            ->map(fn ($event) => EventCardDto::fromModel($event))
            ->toArray();
    }
}
