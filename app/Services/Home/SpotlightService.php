<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SERVICIO: tarjeta destacada del hero (última noticia o próximo evento)
//
//  NUNCA usa ORDER BY RAND() en BD — solo 2 consultas ya optimizadas
//  (latest()/orderBy con índice) + PHP rand() para elegir entre ambas.
//  Cache::remember() evita relanzarlas en cada carga del home.
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Services\Home;

use App\DTOs\Home\SpotlightCardDto;
use App\DTOs\NewsCardDto;
use App\Services\Home\Events\EventService;
use App\Services\NewsService;
use Illuminate\Support\Facades\Cache;

class SpotlightService
{
    private const CACHE_KEY = 'home_tarjeta_destacada';
    private const CACHE_TTL_MINUTOS = 10;

    public function __construct(
        private readonly NewsService  $newsService,
        private readonly EventService $eventService,
    ) {}

    /** Tarjeta cacheada — null si no hay noticias ni eventos disponibles */
    public function obtenerTarjeta(): ?SpotlightCardDto
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTOS),
            fn () => $this->elegir(),
        );
    }

    private function elegir(): ?SpotlightCardDto
    {
        $noticia = $this->newsService->getLatestForHome(1)->first();
        $evento  = $this->eventService->getUpcoming(1)[0] ?? null;

        // Solo uno disponible: se usa ese, sin azar
        if ($noticia === null) {
            return $evento ? SpotlightCardDto::fromEvent($evento) : null;
        }
        if ($evento === null) {
            return SpotlightCardDto::fromNews(NewsCardDto::fromModel($noticia));
        }

        // Ambos disponibles: 50/50 con rand() nativo de PHP
        return rand(0, 1) === 0
            ? SpotlightCardDto::fromNews(NewsCardDto::fromModel($noticia))
            : SpotlightCardDto::fromEvent($evento);
    }
}
