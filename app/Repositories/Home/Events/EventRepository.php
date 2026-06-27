<?php

namespace App\Repositories\Home\Events;

use App\Models\Event;
use Illuminate\Support\Collection;

/**
 * Repositorio de eventos para la sección Home.
 *
 * Namespace alineado con la ruta real del archivo:
 *   app/Repositories/Home/Events/EventRepository.php
 */
class EventRepository
{
    private const CARD_COLUMNS = [
        'id',
        'uuid',
        'name',
        'description',
        'category_id',
        'start_at',
        'end_at',
        'capacity_total',
        'status',
    ];

    /**
     * Próximos N eventos publicados, ordenados cronológicamente (ASC).
     *
     * @param  int $max  Máximo de eventos a retornar.
     * @return Collection<Event>
     */
    public function nextPublished(int $max = 3): Collection
    {
        return Event::select(self::CARD_COLUMNS)
            ->where('status', 'published')
            ->where('start_at', '>', now())
            ->with([
                // Solo portada — una imagen por evento
                'media' => fn ($q) => $q
                    ->select(['id', 'mediable_id', 'mediable_type',
                              'path', 'disk', 'mime', 'alt', 'title', 'order'])
                    ->where('order', 0)
                    ->where('mime', 'like', 'image/%'),

                // Solo nombre de categoría
                'category:id,name',

                // Solo estado de registros — sin datos personales
                'registrations' => fn ($q) => $q
                    ->select(['id', 'event_id', 'status'])
                    ->whereIn('status', ['registered', 'waitlist']),
            ])
            ->orderBy('start_at')
            ->limit($max)
            ->get();
    }
}
