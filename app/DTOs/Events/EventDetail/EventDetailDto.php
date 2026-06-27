<?php

namespace App\DTOs\Events\EventDetail;

use App\Models\Event;
use App\Models\EventCollaborator;
use App\Models\Media;
use App\Support\Maps\GoogleMapsEmbed;
use Illuminate\Support\Collection;
use Livewire\Wireable;

/**
 * DTO inmutable para la página de detalle de un evento.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada del modelo Event a la vista.
 *  - Sanitizar cada campo antes de exponerlo (prevención de XSS).
 *  - Exponer solo los campos necesarios para la vista de detalle.
 *  - Clasificar los archivos multimedia por tipo para renderizado condicional.
 *
 * SEGURIDAD:
 *  - readonly: imposible mutar en la vista o en Livewire.
 *  - strip_tags() + mb_substr() en todos los campos de texto de BD.
 *  - El campo 'content' pasa por sanitizeHtml() — permite HTML seguro.
 *  - UUID como único identificador público — el ID entero nunca se expone.
 *  - Implementa Wireable para serialización segura entre componentes.
 */
readonly class EventDetailDto implements Wireable
{
    // Tags HTML permitidos en el campo 'content' del evento
    private const ALLOWED_HTML_TAGS =
        '<p><h2><h3><h4><strong><em><b><i><ul><ol><li><br><a><blockquote><span>';

    public function __construct(
        // ── Identificación ─────────────────────────────────────────────
        public string  $uuid,
        public string  $title,
        public string  $description,          // texto plano sanitizado
        public string  $content,              // HTML sanitizado — usar con {!! !!}
        public string  $categoryName,

        // ── Temporalidad ───────────────────────────────────────────────
        public string  $startDate,            // "12 de julio de 2025"
        public string  $startTime,            // "09:00"
        public string  $startDateIso,         // "2025-07-12" para <time datetime="">
        public ?string $endTime,              // null si no está definida

        // ── Capacidad ──────────────────────────────────────────────────
        public int     $capacityTotal,
        public int     $registered,           // inscritos activos
        public int     $occupancyPct,         // 0-100
        public bool    $isFull,
        public bool    $requiresRegistration, // true si capacity_total > 0

        // ── Multimedia ─────────────────────────────────────────────────
        // Portada: se muestra como hero, separada de la galería (nunca
        // duplicada como su primera diapositiva) — ver resolverPortada().
        public ?string $coverUrl,
        public string  $coverAlt,

        /** @var array<int, array{url:string, mime:string, alt:string, title:string, isImage:bool, isVideo:bool}> */
        public array   $mediaItems,

        // ── Ubicación — columna 'location' de la tabla events ──────────
        public string  $location,

        /**
         * Colaboradores invitados (socios registrados o externos), ya
         * resueltos a un array plano listo para la vista — ver
         * resolverColaboradores(). Vacío si el evento no tiene ninguno.
         *
         * @var array<int, array{key:string, name:string, logoUrl:?string, participationDetails:?string}>
         */
        public array   $collaborators,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent
    //  Requiere eager load: 'media', 'category', 'registrations'
    // ════════════════════════════════════════════════════════════════════

    public static function fromModel(Event $event): self
    {
        // Inscritos activos (registered + waitlist)
        $registered = $event->registrations
            ->whereIn('status', ['registered', 'waitlist'])
            ->count();

        // Porcentaje de ocupación — protegido contra división por cero
        $pct = $event->capacity_total > 0
            ? (int) min(100, round(($registered / $event->capacity_total) * 100))
            : 0;

        // Todos los archivos multimedia, ordenados por order ASC
        $todosLosMedias = $event->media->sortBy('order')->values();

        // Portada: separada del resto ANTES de construir mediaItems, para
        // que la galería nunca la repita como primera diapositiva.
        $portada = self::resolverPortada($todosLosMedias);

        $mediaItems = $todosLosMedias
            ->reject(fn (Media $m) => $portada !== null && $m === $portada)
            ->map(fn (Media $m) => [
                'url'     => $m->url(),
                'mime'    => $m->mime,
                'alt'     => self::clean($m->alt ?? $event->name, 250),
                'title'   => self::clean($m->title ?? '', 200),
                'isImage' => $m->isImage(),
                'isVideo' => $m->isVideo(),
            ])
            ->values()
            ->toArray();

        return new self(
            uuid:                 $event->uuid,
            title:                self::clean($event->name, 180),
            description:          self::clean($event->description, 500),
            content:              self::sanitizeHtml((string) ($event->content ?? '')),
            categoryName:         self::clean($event->category?->name ?? '', 120),

            startDate:            $event->start_at->translatedFormat('d \d\e F \d\e Y'),
            startTime:            $event->start_at->format('H:i'),
            startDateIso:         $event->start_at->toDateString(),
            endTime:              $event->end_at?->format('H:i'),

            capacityTotal:        $event->capacity_total,
            registered:           $registered,
            occupancyPct:         $pct,
            isFull:               $registered >= $event->capacity_total,
            requiresRegistration: $event->capacity_total > 0,

            coverUrl:             $portada?->url(),
            coverAlt:             self::clean($portada?->alt ?? $event->name, 250),
            mediaItems:           $mediaItems,

            // Columna 'location' (añadida via migración add_location_to_events_table)
            // nullable en BD → string vacío si aún no se ha llenado el campo
            location:             self::clean($event->location ?? '', 300),

            collaborators:        self::resolverColaboradores($event),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  WIREABLE — serialización segura para Livewire
    // ════════════════════════════════════════════════════════════════════

    public function toLivewire(): array
    {
        return [
            'uuid'                 => $this->uuid,
            'title'                => $this->title,
            'description'          => $this->description,
            'content'              => $this->content,
            'categoryName'         => $this->categoryName,
            'startDate'            => $this->startDate,
            'startTime'            => $this->startTime,
            'startDateIso'         => $this->startDateIso,
            'endTime'              => $this->endTime,
            'capacityTotal'        => $this->capacityTotal,
            'registered'           => $this->registered,
            'occupancyPct'         => $this->occupancyPct,
            'isFull'               => $this->isFull,
            'requiresRegistration' => $this->requiresRegistration,
            'coverUrl'             => $this->coverUrl,
            'coverAlt'             => $this->coverAlt,
            'mediaItems'           => $this->mediaItems,
            'location'             => $this->location,
            'collaborators'        => $this->collaborators,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(...$value);
    }

    // ════════════════════════════════════════════════════════════════════
    //  AYUDANTES DE VISTA
    // ════════════════════════════════════════════════════════════════════

    /**
     * URL para el enlace "Abrir en Google Maps" de la vista de detalle.
     * El iframe en sí se construye en <x-google-maps-embed> a partir de
     * $location — ambos delegan a GoogleMapsEmbed (única fuente de verdad).
     */
    public function mapSearchUrl(): ?string
    {
        return GoogleMapsEmbed::searchUrl($this->location);
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Resuelve la portada de $todosLosMedias (ya cargada vía eager loading,
     * sin queries adicionales): preferencia 'collection' === 'cover'; si no
     * hay ninguna, recae en la imagen sin clasificar (collection === null,
     * registros legacy) con el 'order' más bajo — la misma regla que ya usa
     * AdminEventsFormService::resolverPortada() para el formulario admin,
     * así que el primer archivo adjunto a un evento es "la portada" de
     * forma consistente en todo el módulo, sin importar qué vista la pida.
     */
    private static function resolverPortada(Collection $todosLosMedias): ?Media
    {
        return $todosLosMedias->firstWhere('collection', 'cover')
            ?? $todosLosMedias
                ->filter(fn (Media $m) => $m->collection === null && $m->isImage())
                ->sortBy('order')
                ->first();
    }

    /**
     * Resuelve $event->collaborators (ya cargado vía eager loading anidado,
     * ver EventDetailRepository) a un array plano listo para la tarjeta
     * pública. Misma regla de resolución que
     * AdminEventsFormService::resolverColaboradores() para el admin, pero
     * sin los campos de edición (partnerId, customLogoExistingPath, etc.)
     * que esta vista de solo lectura no necesita.
     *
     * Socio registrado  → nombre y logo vienen de Partner (relación
     *                      'partner.media', ya filtrada en memoria por
     *                      collection==='logo', sin query adicional).
     * Colaborador externo (is_custom=true) → nombre y logo vienen de las
     *                      columnas propias custom_name/custom_logo_path;
     *                      el logo se sirve por la misma ruta 'media.show'
     *                      que Media::url() (ver docblock de la migración
     *                      2026_06_21_000000_create_event_partner_table).
     */
    private static function resolverColaboradores(Event $event): array
    {
        return $event->collaborators
            ->map(function (EventCollaborator $fila) {
                if ($fila->is_custom) {
                    return [
                        'key'                  => 'custom-' . $fila->id,
                        'name'                 => self::clean($fila->custom_name ?? '', 150),
                        'logoUrl'              => $fila->custom_logo_path !== null
                            ? route('media.show', ['path' => $fila->custom_logo_path])
                            : null,
                        'participationDetails' => self::clean($fila->participation_details ?? '', 300) ?: null,
                    ];
                }

                $logo = $fila->partner?->media->firstWhere('collection', 'logo');

                return [
                    'key'                  => 'partner-' . $fila->partner_id,
                    'name'                 => self::clean($fila->partner?->name ?? '', 150),
                    'logoUrl'              => $logo?->url(),
                    'participationDetails' => self::clean($fila->participation_details ?? '', 300) ?: null,
                ];
            })
            ->values()
            ->all();
    }

    /** Elimina HTML y limita longitud — previene XSS de texto plano. */
    private static function clean(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }

    /**
     * Sanitiza HTML del campo 'content':
     *  - Permite los tags de la allowlist.
     *  - Elimina <script>, <iframe>, <style> y tags no permitidos.
     *  - Elimina atributos de evento (onclick, onerror, etc.).
     *  - Elimina href=javascript: para prevenir XSS en enlaces.
     *
     * NOTA PRODUCCIÓN: reemplazar por HTMLPurifier para protección completa.
     *   composer require ezyang/htmlpurifier
     */
    private static function sanitizeHtml(string $html): string
    {
        $clean = strip_tags($html, self::ALLOWED_HTML_TAGS);
        $clean = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);
        $clean = preg_replace('/\b(href|src)\s*=\s*["\']javascript:[^"\']*["\']/i', '', $clean);
        return $clean ?? '';
    }
}
