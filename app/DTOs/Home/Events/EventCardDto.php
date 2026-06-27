<?php

namespace App\DTOs\Home\Events;

use App\Models\Event;
use Livewire\Wireable;

/**
 * DTO inmutable para la tarjeta de resumen de un evento (Home slider).
 *
 * Namespace alineado con la ruta real del archivo:
 *   app/DTOs/Home/Events/EventCardDto.php
 */
readonly class EventCardDto implements Wireable
{
    public function __construct(
        public string  $uuid,
        public string  $title,
        public string  $description,
        public string  $categoryName,
        public string  $startDate,
        public string  $startTime,
        public string  $startDateIso,
        public ?string $endTime,
        public int     $capacityTotal,
        public int     $registered,
        public int     $occupancyPct,
        public bool    $isFull,
        public ?string $coverUrl,
        public ?string $coverAlt,
    ) {}

    public static function fromModel(Event $event): self
    {
        $cover = $event->media->first(
            fn ($m) => str_starts_with($m->mime, 'image/') && $m->order === 0
        );

        $registered = $event->registrations
            ->whereIn('status', ['registered', 'waitlist'])
            ->count();

        $pct = $event->capacity_total > 0
            ? (int) min(100, round(($registered / $event->capacity_total) * 100))
            : 0;

        return new self(
            uuid:          $event->uuid,
            title:         self::clean($event->name, 180),
            description:   self::clean($event->description, 300),
            categoryName:  self::clean($event->category?->name ?? '', 120),
            startDate:     $event->start_at->translatedFormat('d M Y'),
            startTime:     $event->start_at->format('H:i'),
            startDateIso:  $event->start_at->toDateString(),
            endTime:       $event->end_at?->format('H:i'),
            capacityTotal: $event->capacity_total,
            registered:    $registered,
            occupancyPct:  $pct,
            isFull:        $registered >= $event->capacity_total,
            coverUrl:      $cover?->url(),
            coverAlt:      $cover ? self::clean($cover->alt ?? $event->name, 250) : null,
        );
    }

    public function toLivewire(): array
    {
        return [
            'uuid'          => $this->uuid,
            'title'         => $this->title,
            'description'   => $this->description,
            'categoryName'  => $this->categoryName,
            'startDate'     => $this->startDate,
            'startTime'     => $this->startTime,
            'startDateIso'  => $this->startDateIso,
            'endTime'       => $this->endTime,
            'capacityTotal' => $this->capacityTotal,
            'registered'    => $this->registered,
            'occupancyPct'  => $this->occupancyPct,
            'isFull'        => $this->isFull,
            'coverUrl'      => $this->coverUrl,
            'coverAlt'      => $this->coverAlt,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(...$value);
    }

    private static function clean(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
