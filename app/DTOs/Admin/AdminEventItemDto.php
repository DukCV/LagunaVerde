<?php

namespace App\DTOs\Admin;

use App\Models\Event;

/**
 * DTO de ítem de evento para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada de datos del modelo Event a las vistas admin.
 *  - Sanitizar cada campo antes de exponerlo para prevenir XSS en salida.
 *  - Calcular las reglas de capacidad (incluyendo "0 = ilimitado") una sola vez,
 *    para que la vista nunca repita esta lógica de negocio.
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * REGLA DE NEGOCIO — CAPACIDAD ILIMITADA:
 *  capacity_total = 0 se interpreta como "sin límite de aforo" únicamente
 *  aquí y en la vista administrativa. Esto evita alterar la columna
 *  unsignedInteger en la tabla 'events' (ya en producción) para representar
 *  null/ilimitado, manteniendo la migración existente intacta.
 */
readonly class AdminEventItemDto
{
    public function __construct(
        public int     $id,             // Solo para operaciones internas de admin (wire:click)
        public string  $uuid,           // Identificador público (reservado para uso futuro)
        public string  $title,
        public string  $excerpt,        // Descripción recortada para la tarjeta
        public string  $categoryName,
        public string  $status,         // 'draft' | 'published' | 'cancelled' | 'closed'
        public string  $statusLabel,    // Etiqueta en español para la vista
        public string  $location,
        public string  $startDateLabel, // "12 jul 2026"
        public string  $startTimeLabel, // "09:00"
        public string  $endDateLabel,
        public string  $endTimeLabel,
        public bool    $isSameDay,      // true si inicio y fin caen el mismo día
        public string  $createdAtLabel, // "Publicado": fecha de creación del registro
        public string  $updatedAtLabel, // "Actualizado"
        public int     $registrations,  // Inscritos activos (registered + waitlist)
        public int     $capacityTotal,  // Valor crudo de la BD (0 = ilimitado)
        public bool    $isUnlimited,
        public int     $occupancyPct,   // 0 cuando es ilimitado
        public bool    $isFull,         // false cuando es ilimitado
        public bool    $isAlmostFull,   // >= 80% de ocupación, sin estar lleno
        public ?string $coverUrl,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent cargado
    // ════════════════════════════════════════════════════════════════════

    /**
     * Transforma un modelo Event en DTO.
     *
     * El modelo debe llegar con eager loading de:
     *  - 'category:id,name'
     *  - 'media' (solo se usa la portada con order = 0)
     *  - registrations_count cargado vía withCount() con filtro de estados activos
     *
     * SEGURIDAD XSS:
     *  - La protección XSS se delega exclusivamente a Blade mediante {{ }}.
     *  - mb_substr() limita longitud máxima → evita strings gigantes en memoria.
     */
    public static function fromModel(Event $event): self
    {
        // Portada: primera imagen con order = 0 (misma convención que EventSummaryDto)
        $cover = $event->media->first(
            fn ($m) => $m->isImage() && $m->order === 0
        );

        $registrations = (int) ($event->registrations_count ?? 0);
        $isUnlimited   = $event->capacity_total === 0;
        $occupancyPct  = $event->occupancyPercent($registrations);
        $isFull        = ! $isUnlimited && $registrations >= $event->capacity_total;
        $isAlmostFull  = ! $isUnlimited && ! $isFull && $occupancyPct >= 80;

        $isSameDay = $event->start_at->isSameDay($event->end_at);

        return new self(
            id:             $event->id,
            uuid:           $event->uuid,
            title:          self::sanitize($event->name, 180),
            excerpt:        self::sanitize($event->description, 220),
            categoryName:   self::sanitize($event->category?->name ?? '—', 120),
            status:         $event->status,
            statusLabel:    self::translateStatus($event->status),
            location:       self::sanitize($event->location ?? '', 300),

            startDateLabel: $event->start_at->translatedFormat('d M Y'),
            startTimeLabel: $event->start_at->format('H:i'),
            endDateLabel:   $event->end_at->translatedFormat('d M Y'),
            endTimeLabel:   $event->end_at->format('H:i'),
            isSameDay:      $isSameDay,

            createdAtLabel: $event->created_at->translatedFormat('d M Y'),
            updatedAtLabel: $event->updated_at->translatedFormat('d M Y'),

            registrations:  $registrations,
            capacityTotal:  $event->capacity_total,
            isUnlimited:    $isUnlimited,
            occupancyPct:   $occupancyPct,
            isFull:         $isFull,
            isAlmostFull:   $isAlmostFull,

            coverUrl:       $cover?->url(),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Traduce el estado de BD al español para mostrar en la interfaz */
    private static function translateStatus(string $status): string
    {
        return match ($status) {
            'published' => 'Publicado',
            'draft'     => 'Borrador',
            'cancelled' => 'Cancelado',
            'closed'    => 'Finalizado',
            default     => ucfirst($status),
        };
    }

    /**
     * Elimina HTML, normaliza espacios y limita longitud máxima.
     * XSS prevenido por Blade con {{ }} en cada punto de salida de la vista.
     */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $maxLength);
    }
}
