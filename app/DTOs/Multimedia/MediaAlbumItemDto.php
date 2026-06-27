<?php

namespace App\DTOs\Multimedia;

use App\Models\Media;

/**
 * DTO inmutable para un elemento de media dentro de un álbum de la galería.
 *
 * Encapsula la lógica de clasificación (imagen vs. video) y la generación
 * de URLs, aislando la vista de cualquier contacto con el modelo Eloquent.
 *
 * SEGURIDAD:
 *  - Propiedades readonly → inmutabilidad garantizada después de la construcción.
 *  - strip_tags() + mb_substr() en textos antes de exponerlos al frontend.
 *  - La URL se genera vía Media::url() (ruta 'media.show') → nunca se
 *    exponen rutas internas del disco, y no depende del enlace simbólico
 *    de 'storage:link' (ver App\Http\Controllers\MediaController).
 */
readonly class MediaAlbumItemDto
{
    // ── Longitudes máximas para sanitización de cadenas ───────────────────
    private const MAX_TITLE_LENGTH = 200;
    private const MAX_ALT_LENGTH   = 250;

    public function __construct(
        /** Identificador del registro de media (usado como wire:key) */
        public int    $id,
        /** URL pública del archivo (local o externa) */
        public string $url,
        /** URL de miniatura: igual a $url para imágenes; thumbnail o url para videos */
        public string $thumbnailUrl,
        /** Texto alternativo accesible */
        public string $alt,
        /** Título descriptivo del archivo */
        public string $title,
        /** MIME type completo, p.ej. "image/jpeg" o "video/mp4" */
        public string $mime,
        /** true cuando el archivo es una imagen (MIME comienza con "image/") */
        public bool   $isImage,
        /** true cuando el archivo es un video (MIME comienza con "video/" o URL externa) */
        public bool   $isVideo,
    ) {}

    /**
     * Construye el DTO a partir de un modelo Media de Eloquent.
     *
     * Detección de tipo:
     *  - Si la ruta comienza con "http" → es un recurso externo (YouTube, Vimeo, etc.)
     *    y se clasifica como video independientemente del MIME almacenado.
     *  - En caso contrario → se clasifica por MIME type (image/* o video/*).
     */
    public static function fromModel(Media $media): self
    {
        // Determinar si la ruta apunta a una URL externa o a un archivo local
        $isExternal = str_starts_with($media->path, 'http');

        // Media::url() ya resuelve ambos casos (externo tal cual, o servido
        // vía la ruta 'media.show') — única fuente de verdad, sin duplicar
        // aquí la construcción de la URL.
        $url = $media->url();

        // Clasificar tipo de contenido
        $isVideo = $isExternal || str_starts_with($media->mime, 'video/');
        $isImage = ! $isVideo && str_starts_with($media->mime, 'image/');

        // Miniatura: para videos locales usa la misma URL; para externos usa el campo alt o url
        $thumbnailUrl = $isImage ? $url : ($media->thumbnail ?? $url);

        return new self(
            id:           $media->id,
            url:          $url,
            thumbnailUrl: $thumbnailUrl,
            alt:          self::sanitize($media->alt   ?? '', self::MAX_ALT_LENGTH),
            title:        self::sanitize($media->title ?? basename($media->path), self::MAX_TITLE_LENGTH),
            mime:         $media->mime,
            isImage:      $isImage,
            isVideo:      $isVideo,
        );
    }

    // ── Helpers privados ──────────────────────────────────────────────────

    /** Elimina etiquetas HTML y limita la longitud de una cadena de texto */
    private static function sanitize(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }
}
