<?php

namespace App\DTOs;

use App\Models\News;
use Livewire\Wireable;

/**
 * DTO ligero para los ítems del sidebar "Últimas Noticias".
 *
 * Archivo separado de CommentDto para cumplir PSR-4:
 * un archivo = una clase, nombre de archivo = nombre de clase.
 */
readonly class SidebarNewsDto implements Wireable
{
    public function __construct(
        public string  $uuid,
        public string  $title,
        public string  $categoryName,
        public string  $publishedAt,
        public string  $publishedAtIso,
        public ?string $coverUrl,
    ) {}

    public static function fromModel(News $news): self
    {
        // Portada: collection='cover' es prioritario.
        // Fallback legacy: primera imagen por MIME y order cuando collection es null.
        $cover = $news->media->first(
            fn ($m) => $m->collection === 'cover'
                || ($m->collection === null && str_starts_with($m->mime, 'image/') && $m->order === 0)
        );

        // NOTA DE SEGURIDAD (XSS):
        // Se elimina el uso de strip_tags(). La protección contra XSS 
        // depende exclusivamente del motor de plantillas Blade usando {{ }}.
        return new self(
            uuid:           $news->uuid,
            title:          mb_substr(trim($news->title), 0, 220),
            categoryName:   mb_substr(trim($news->category?->name ?? ''), 0, 120),
            publishedAt:    $news->published_at->translatedFormat('d M Y'),
            publishedAtIso: $news->published_at->toDateString(),
            coverUrl:       $cover?->url(),
        );
    }

    public function toLivewire(): array
    {
        return (array) $this;
    }

    public static function fromLivewire($value): static
    {
        return new static(...$value);
    }
}
