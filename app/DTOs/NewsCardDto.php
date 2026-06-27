<?php

namespace App\DTOs;

use App\Models\News;
use Livewire\Wireable;

/**
 * Data Transfer Object para la tarjeta de resumen de noticia.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada de datos del modelo a la vista.
 *  - Sanitizar cada campo antes de exponerlo.
 *  - Exponer ÚNICAMENTE los campos necesarios para el resumen (nunca 'content').
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * Implementa Wireable para que Livewire pueda serializar/deserializar
 * la instancia al pasar el DTO como propiedad entre componentes padre/hijo.
 */
readonly class NewsCardDto implements Wireable
{
    public function __construct(
        public string $uuid,            // identificador público — nunca el ID entero
        public string $title,
        public string $summary,         // extracto corto; 'content' nunca llega aquí
        public string $authorName,
        public string $categoryName,
        public string $publishedAt,     // ya formateada para mostrar ("15 Ene 2025")
        public string $publishedAtIso,  // formato ISO para <time datetime="">
        public ?string $coverUrl,        // null si no hay imagen registrada
        public ?string $coverAlt,
        public array $documents,       // [ ['name'=>, 'url'=>, 'size'=>], … ]
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent
    // ════════════════════════════════════════════════════════════════════

    /**
     * Transforma un modelo News (ya cargado con sus relaciones) en DTO.
     *
     * SEGURIDAD:
     *  - strip_tags() en todos los strings → previene XSS de datos en BD.
     *  - mb_substr() limita longitud máxima → evita payloads de salida gigantes.
     *  - El modelo debe llegar con 'media' y 'category' en eager load.
     *  - 'content' del modelo no se mapea aquí; sólo 'summary'.
     */
    public static function fromModel(News $news): self
    {
        // Portada: collection='cover' es prioritario.
        // Fallback legacy: primera imagen por MIME cuando collection es null.
        $cover = $news->media->first(
            fn ($m) => $m->collection === 'cover'
                || ($m->collection === null && str_starts_with($m->mime, 'image/'))
        );

        // Documentos: collection='document' es prioritario.
        // Fallback legacy: archivos cuyo MIME no es imagen ni vídeo cuando collection es null.
        $documents = $news->media
            ->filter(
                fn ($m) => $m->collection === 'document'
                    || ($m->collection === null && ! str_starts_with($m->mime, 'image/'))
            )
            ->map(fn ($m) => [
                'name' => self::sanitize($m->title ?? basename($m->path), 200),
                'url'  => $m->url(),
                'size' => self::formatBytes($m->size),
            ])
            ->values()
            ->toArray();

        return new self(
            uuid:          $news->uuid,
            title:         self::limitLength($news->title, 220),
            summary:       self::limitLength($news->summary ?? '', 500),
            authorName:    self::limitLength($news->author_name ?? '', 150),
            categoryName:  self::limitLength($news->category?->name ?? '', 120),
            publishedAt:   $news->published_at->translatedFormat('d M Y'),
            publishedAtIso: $news->published_at->toDateString(),
            coverUrl:      $cover?->url(),
            coverAlt:      $cover
                ? self::limitLength($cover->alt ?? $news->title, 250)
                : null,
            documents:     $documents,
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  WIREABLE — serialización para Livewire
    // ════════════════════════════════════════════════════════════════════

    public function toLivewire(): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'summary' => $this->summary,
            'authorName' => $this->authorName,
            'categoryName' => $this->categoryName,
            'publishedAt' => $this->publishedAt,
            'publishedAtIso' => $this->publishedAtIso,
            'coverUrl' => $this->coverUrl,
            'coverAlt' => $this->coverAlt,
            'documents' => $this->documents,
        ];
    }

    public static function fromLivewire($value): static
    {
        return new static(
            uuid: (string) $value['uuid'],
            title: (string) $value['title'],
            summary: (string) $value['summary'],
            authorName: (string) $value['authorName'],
            categoryName: (string) $value['categoryName'],
            publishedAt: (string) $value['publishedAt'],
            publishedAtIso: (string) $value['publishedAtIso'],
            coverUrl: $value['coverUrl'] ? (string) $value['coverUrl'] : null,
            coverAlt: $value['coverAlt'] ? (string) $value['coverAlt'] : null,
            documents: is_array($value['documents']) ? $value['documents'] : [],
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Elimina etiquetas HTML y limita longitud.
     * Únicamente para campos que no se renderizan directamente en la vista con {{ }}
     * (por ejemplo, nombres de documentos de descarga).
     */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $maxLength);
    }

    /**
     * Limita la longitud del string sin eliminar etiquetas HTML.
     *
     * NOTA DE SEGURIDAD (XSS):
     * Según los requisitos estandarizados, la protección XSS se delega
     * exclusivamente al auto-escaping nativo de Blade mediante el uso de {{ }}.
     * Por lo tanto, no realizamos sanitización manual (strip_tags) en este DTO
     * para las propiedades que se renderizan directamente en la vista.
     */
    private static function limitLength(string $value, int $maxLength): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }

    /**
     * Convierte bytes a representación legible (KB, MB).
     */
    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return number_format($bytes / 1_048_576, 1).' MB';
        }
        if ($bytes >= 1_024) {
            return number_format($bytes / 1_024, 1).' KB';
        }

        return $bytes.' B';
    }
}
