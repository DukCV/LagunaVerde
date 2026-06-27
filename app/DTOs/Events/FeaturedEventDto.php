<?php

namespace App\DTOs\Events;

use App\Models\Event;

/**
 * DTO inmutable para el banner del evento destacado (el más próximo).
 *
 * Separado de EventSummaryDto porque el banner necesita campos
 * adicionales (descripción más larga, formato de fecha diferente)
 * y tiene su propio componente Livewire.
 *
 * No implementa Wireable porque FeaturedEventBanner lo recibe
 * como array serializado desde EventsIndex, no como propiedad directa.
 */
readonly class FeaturedEventDto
{
    public function __construct(
        // ── Identificación ─────────────────────────────────────────────
        public string  $uuid,
        public string  $title,
        public string  $description,    // descripción completa para el banner
        public string  $categoryName,

        // ── Temporalidad ───────────────────────────────────────────────
        public string  $startDate,      // "12 de julio de 2025"
        public string  $startDay,       // "12"
        public string  $startMonth,     // "JUL"
        public string  $startTime,      // "09:00"
        public string  $startDateIso,
        public ?string $endTime,        // null si no está definida

        // ── Capacidad ──────────────────────────────────────────────────
        public int     $capacityTotal,
        public int     $registered,
        public int     $occupancyPct,
        public bool    $isFull,

        // ── Multimedia ─────────────────────────────────────────────────
        public ?string $coverUrl,
        public ?string $coverAlt,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY
    //  Requiere eager load: 'media', 'category', 'registrations'
    // ════════════════════════════════════════════════════════════════════
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
            description:   self::clean($event->description, 500),
            categoryName:  self::clean($event->category?->name ?? '', 120),

            startDate:     $event->start_at->translatedFormat('d \d\e F \d\e Y'),
            startDay:      $event->start_at->format('d'),
            startMonth:    mb_strtoupper($event->start_at->translatedFormat('M')),
            startTime:     $event->start_at->format('H:i'),
            startDateIso:  $event->start_at->toDateString(),
            endTime:       $event->end_at?->format('H:i'),

            capacityTotal: $event->capacity_total,
            registered:    $registered,
            occupancyPct:  $pct,
            isFull:        $registered >= $event->capacity_total,

            coverUrl:      $cover?->url(),
            coverAlt:      $cover
                               ? self::clean($cover->alt ?? $event->name, 250)
                               : null,
        );
    }

    /** Serializa a array plano para pasar entre componentes Livewire. */
    public function toArray(): array
    {
        return [
            'uuid'          => $this->uuid,
            'title'         => $this->title,
            'description'   => $this->description,
            'categoryName'  => $this->categoryName,
            'startDate'     => $this->startDate,
            'startDay'      => $this->startDay,
            'startMonth'    => $this->startMonth,
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

    // ── Helper privado ───────────────────────────────────────────────────
    private static function clean(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
