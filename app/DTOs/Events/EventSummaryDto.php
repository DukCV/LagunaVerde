<?php

namespace App\DTOs\Events;

use App\Models\Event;
use Livewire\Wireable;

/**
 * DTO inmutable para la tarjeta de resumen de un evento en el listado.
 *
 * SEGURIDAD:
 *  - readonly: imposible mutar en vista o Livewire.
 *  - strip_tags() + mb_substr() en todos los strings de BD.
 *  - UUID como único identificador público — ID entero nunca se expone.
 *  - Implementa Wireable para serialización segura entre componentes.
 */
readonly class EventSummaryDto implements Wireable
{
    public function __construct(
        // ── Identificación ─────────────────────────────────────────────
        public string  $uuid,
        public string  $title,
        public string  $description,
        public string  $categoryName,

        // ── Temporalidad ───────────────────────────────────────────────
        public string  $startDate,       // "12 Jul 2025" — para mostrar
        public string  $startDay,        // "12" — para el badge de fecha
        public string  $startMonth,      // "JUL" — para el badge de fecha
        public string  $startTime,       // "09:00"
        public string  $startDateIso,    // "2025-07-12" para <time datetime="">
        public ?string $endTime,         // null si no está definida

        // ── Estado ─────────────────────────────────────────────────────
        public bool    $isActive,        // true = próximo, false = finalizado
        public string  $statusLabel,     // "Próximo" | "Finalizado" | "Cancelado"

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
    //  FACTORY — construye el DTO desde un modelo Eloquent
    //  Requiere eager load: 'media', 'category', 'registrations'
    // ════════════════════════════════════════════════════════════════════
    public static function fromModel(Event $event): self
    {
        // Portada: primera imagen con order = 0
        $cover = $event->media->first(
            fn ($m) => str_starts_with($m->mime, 'image/') && $m->order === 0
        );

        // Inscritos activos (registered + waitlist)
        $registered = $event->registrations
            ->whereIn('status', ['registered', 'waitlist'])
            ->count();

        // Porcentaje de ocupación — protegido contra división por cero
        $pct = $event->capacity_total > 0
            ? (int) min(100, round(($registered / $event->capacity_total) * 100))
            : 0;

        // Clasificación de estado para orden y estilos visuales
        $isActive    = in_array($event->status, ['published']) && $event->start_at->isFuture();
        $statusLabel = match(true) {
            $event->status === 'cancelled'  => 'Cancelado',
            $event->start_at->isPast()      => 'Finalizado',
            default                         => 'Próximo',
        };

        return new self(
            uuid:          $event->uuid,
            title:         self::clean($event->name, 180),
            description:   self::clean($event->description, 300),
            categoryName:  self::clean($event->category?->name ?? '', 120),

            startDate:     $event->start_at->translatedFormat('d M Y'),
            startDay:      $event->start_at->format('d'),
            startMonth:    mb_strtoupper($event->start_at->translatedFormat('M')),
            startTime:     $event->start_at->format('H:i'),
            startDateIso:  $event->start_at->toDateString(),
            endTime:       $event->end_at?->format('H:i'),

            isActive:      $isActive,
            statusLabel:   $statusLabel,

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

    // ── Wireable ─────────────────────────────────────────────────────────
    public function toLivewire(): array
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
            'isActive'      => $this->isActive,
            'statusLabel'   => $this->statusLabel,
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

    // ── Helper privado ───────────────────────────────────────────────────
    private static function clean(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
