<?php

namespace App\DTOs;

use App\Models\News;
use Livewire\Wireable;

/**
 * DTO inmutable para la página de detalle de una noticia.
 *
 * SEGURIDAD:
 *  - readonly: imposible mutar en la vista.
 *  - strip_tags() con allowlist en 'content': permite HTML seguro,
 *    elimina scripts, iframes y atributos de evento (onclick, etc.).
 *  - Todos los campos de texto pasan por sanitize().
 *  - 'uuid' como único identificador público — el ID entero no se expone.
 *  - Implementa Wireable para serialización segura entre componentes Livewire.
 *
 * IMPORTANTE: En producción usa HTMLPurifier (ezyang/htmlpurifier)
 * para sanitización completa del campo 'content'.
 * composer require ezyang/htmlpurifier
 */
readonly class NewsDetailDto implements Wireable
{
    // Tags HTML permitidos en el contenido de la noticia.
    // No se permiten: <script> <iframe> <form> <input> <style> ni atributos de evento.
    // <div>, <h1>, <del> y <pre>/<code> son generados por el editor Trix;
    // <p>, <h2>, <h3>, <h4> se conservan para noticias antiguas creadas con Quill.
    private const ALLOWED_CONTENT_TAGS = '<div><p><h1><h2><h3><h4><strong><em><b><i>'
        . '<del><pre><code><ul><ol><li><blockquote><br><a><span><figure><figcaption>';

    public function __construct(
        public string  $uuid,
        public string  $title,
        public string  $content,        // HTML saneado — renderizar con {!! !!}
        public string  $summary,
        public string  $authorName,
        public string  $categoryName,
        public string  $publishedAt,    // "15 Ene 2025"
        public string  $publishedAtIso, // "2025-01-15"

        /** @var MediaItemDto[] Imágenes (excluye la portada, order > 0) */
        public array   $galleryImages,

        /** @var MediaItemDto[] Videos adjuntos */
        public array   $videos,

        /** @var MediaItemDto[] PDFs y otros documentos */
        public array   $documents,

        /** @var MediaItemDto|null Imagen de portada principal (order = 0) */
        public ?MediaItemDto $coverImage,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY
    // ════════════════════════════════════════════════════════════════════

    /**
     * Construye el DTO desde un modelo News con sus relaciones ya cargadas.
     * Requiere eager load de: 'media', 'category'.
     */
    public static function fromModel(News $news): self
    {
        // Convertir todos los media en DTOs, ordenados por 'order'
        $allMedia = $news->media
            ->sortBy('order')
            ->map(fn ($m) => MediaItemDto::fromModel($m));

        return new self(
            uuid:           $news->uuid,
            title:          self::sanitize($news->title, 220),
            // toHtml() devuelve el fragmento "crudo" (sin el wrapper
            // <div class="trix-content"> que añade el render por defecto del
            // paquete); el wrapper se aplica una sola vez en la vista de detalle.
            content:        self::sanitizeHtml($news->content->toHtml()),
            summary:        self::sanitize($news->summary ?? '', 500),
            authorName:     self::sanitize($news->author_name ?? '', 150),
            categoryName:   self::sanitize($news->category?->name ?? '', 120),
            publishedAt:    $news->published_at->translatedFormat('d M Y'),
            publishedAtIso: $news->published_at->toDateString(),

            // Portada: collection='cover' es prioritario.
            // Fallback legacy: primera imagen con order=0 cuando collection es null.
            coverImage: $allMedia->first(
                fn ($m) => $m->collection === 'cover'
                    || ($m->collection === '' && $m->isImage && $m->order === 0)
            ),

            // Galería: collection='slider' es prioritario.
            // Fallback legacy: imágenes con order>0 cuando collection es null.
            galleryImages: $allMedia
                ->filter(
                    fn ($m) => $m->collection === 'slider'
                        || ($m->collection === '' && $m->isImage && $m->order > 0)
                )
                ->values()
                ->toArray(),

            // Vídeos: clasificados por MIME (los vídeos siempre van al slider).
            videos: $allMedia
                ->filter(fn ($m) => $m->isVideo)
                ->values()
                ->toArray(),

            // Documentos: collection='document' es prioritario.
            // Fallback legacy: archivos que no son imagen ni vídeo cuando collection es null.
            documents: $allMedia
                ->filter(
                    fn ($m) => $m->collection === 'document'
                        || ($m->collection === '' && $m->isDocument)
                )
                ->values()
                ->toArray(),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  WIREABLE — serialización para pasar el DTO a componentes hijo
    // ════════════════════════════════════════════════════════════════════

    public function toLivewire(): array
    {
        return [
            'uuid'           => $this->uuid,
            'title'          => $this->title,
            'content'        => $this->content,
            'summary'        => $this->summary,
            'authorName'     => $this->authorName,
            'categoryName'   => $this->categoryName,
            'publishedAt'    => $this->publishedAt,
            'publishedAtIso' => $this->publishedAtIso,
            'galleryImages'  => array_map(fn ($m) => (array) $m, $this->galleryImages),
            'videos'         => array_map(fn ($m) => (array) $m, $this->videos),
            'documents'      => array_map(fn ($m) => (array) $m, $this->documents),
            'coverImage'     => $this->coverImage ? (array) $this->coverImage : null,
        ];
    }

    public static function fromLivewire($value): static
    {
        $toDto = fn (?array $data) => $data ? new MediaItemDto(...$data) : null;
        $toDtos = fn (array $items) => array_map(fn ($i) => new MediaItemDto(...$i), $items);

        return new static(
            uuid:           $value['uuid'],
            title:          $value['title'],
            content:        $value['content'],
            summary:        $value['summary'],
            authorName:     $value['authorName'],
            categoryName:   $value['categoryName'],
            publishedAt:    $value['publishedAt'],
            publishedAtIso: $value['publishedAtIso'],
            galleryImages:  $toDtos($value['galleryImages']),
            videos:         $toDtos($value['videos']),
            documents:      $toDtos($value['documents']),
            coverImage:     $toDto($value['coverImage']),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════════════

    /** Sanitiza texto plano: elimina tags, limita longitud. */
    private static function sanitize(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }

    /**
     * Sanitiza HTML del campo 'content':
     *  - Permite tags de la allowlist (headings, párrafos, listas, citas, links).
     *  - Elimina <script>, <iframe>, <style> y cualquier tag no permitido.
     *
     * NOTA PRODUCCIÓN: Reemplazar por HTMLPurifier para protección completa
     * contra atributos maliciosos (href=javascript:, onclick, etc.).
     */
    private static function sanitizeHtml(string $html): string
    {
        // Paso 1: strip_tags con allowlist
        $clean = strip_tags($html, self::ALLOWED_CONTENT_TAGS);

        // Paso 2: eliminar atributos de evento (onclick, onerror, onload…)
        // Expresión regular que elimina cualquier atributo on*="..."
        $clean = preg_replace('/\bon\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);

        // Paso 3: eliminar javascript: en href/src
        $clean = preg_replace('/\b(href|src)\s*=\s*["\']javascript:[^"\']*["\']/i', '', $clean);

        return $clean;
    }
}
