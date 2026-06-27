<?php

namespace App\DTOs\Home;

use App\DTOs\Home\Events\EventCardDto;
use App\DTOs\NewsCardDto;

/**
 * DTO normalizado para la tarjeta destacada del hero (Noticia o Evento).
 * Unifica ambos orígenes en una sola forma para que la vista no necesite
 * distinguir el tipo — ver App\Services\Home\SpotlightService.
 */
readonly class SpotlightCardDto
{
    public function __construct(
        public string  $tipoLabel, // 'Noticia' | 'Evento' — texto del badge
        public string  $titulo,
        public string  $fecha,     // ya formateada
        public ?string $coverUrl,
        public ?string $coverAlt,
        public string  $url,       // ruta de detalle ya resuelta
    ) {}

    public static function fromNews(NewsCardDto $noticia): self
    {
        return new self(
            tipoLabel: 'Noticia',
            titulo:    $noticia->title,
            fecha:     $noticia->publishedAt,
            coverUrl:  $noticia->coverUrl,
            coverAlt:  $noticia->coverAlt,
            url:       route('news.show', $noticia->uuid),
        );
    }

    public static function fromEvent(EventCardDto $evento): self
    {
        return new self(
            tipoLabel: 'Evento',
            titulo:    $evento->title,
            fecha:     $evento->startDate,
            coverUrl:  $evento->coverUrl,
            coverAlt:  $evento->coverAlt,
            url:       route('events.show', $evento->uuid),
        );
    }
}
