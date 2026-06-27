<?php

namespace App\DTOs;

use App\Models\Media;

/**
 * DTO inmutable para un archivo de media asociado a una noticia.
 *
 * CLASIFICACIÓN:
 *  Se usa el campo 'collection' como criterio primario ('cover'|'slider'|'document').
 *  Cuando collection es null (registros legacy anteriores a la migración), se
 *  recae en la clasificación por MIME type para mantener compatibilidad.
 *
 * SEGURIDAD:
 *  - readonly: imposible mutar en la vista.
 *  - sanitize() elimina tags y limita longitud en campos de texto.
 *  - url() de Storage usa el disco configurado → sin rutas hardcodeadas.
 */
readonly class MediaItemDto
{
    public function __construct(
        public string  $url,
        public string  $mime,
        public string  $collection,   // 'cover' | 'slider' | 'document' | '' (legacy)
        public string  $title,
        public string  $alt,
        public string  $size,         // formateado: "2.3 MB"
        public int     $order,
        public bool    $isImage,
        public bool    $isVideo,
        public bool    $isDocument,
    ) {}

    /**
     * Construye el DTO desde un modelo Media de Eloquent.
     * La clasificación usa 'collection' y hace fallback a MIME type si es null.
     */
    public static function fromModel(Media $media): self
    {
        $mime = $media->mime;

        // Clasificación por MIME — fuente de verdad para isImage/isVideo/isDocument
        $isImage    = str_starts_with($mime, 'image/');
        $isVideo    = str_starts_with($mime, 'video/');
        $isDocument = ! $isImage && ! $isVideo;

        return new self(
            url:        $media->url(),
            mime:       $mime,
            // Normalizar a string vacío cuando collection es null (registros legacy)
            collection: $media->collection ?? '',
            title:      self::sanitize($media->title ?? basename($media->path), 200),
            alt:        self::sanitize($media->alt   ?? '', 250),
            size:       self::formatBytes($media->size),
            order:      $media->order ?? 0,
            isImage:    $isImage,
            isVideo:    $isVideo,
            isDocument: $isDocument,
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS
    // ════════════════════════════════════════════════════════════════════

    /** Elimina etiquetas HTML y limita la longitud del string. */
    private static function sanitize(string $value, int $max): string
    {
        return mb_substr(strip_tags(trim($value)), 0, $max);
    }

    /** Convierte bytes en representación legible (B, KB, MB). */
    private static function formatBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 1) . ' MB',
            $bytes >= 1_024     => number_format($bytes / 1_024, 1)     . ' KB',
            default             => $bytes . ' B',
        };
    }
}
