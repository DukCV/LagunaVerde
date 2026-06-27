<?php

namespace App\DTOs\Admin;

use App\Models\News;

/**
 * DTO de ítem de noticia para el panel de administración.
 *
 * RESPONSABILIDADES:
 *  - Ser la única puerta de entrada de datos del modelo News a las vistas admin.
 *  - Sanitizar cada campo antes de exponerlo para prevenir XSS en salida.
 *  - Exponer ÚNICAMENTE los campos del listado — 'content' nunca se incluye aquí.
 *  - Ser inmutable (readonly) para evitar mutaciones accidentales en la vista.
 *
 * A diferencia del DTO público, expone estado 'draft'/'archived' ya que el
 * administrador gestiona noticias en todos sus estados.
 */
readonly class AdminNewsItemDto
{
    public function __construct(
        public int     $id,            // Solo para operaciones internas de admin
        public string  $uuid,          // Identificador público para URLs
        public string  $title,
        public string  $summary,       // Resumen breve para mostrar en tarjetas
        public string  $authorName,
        public string  $categoryName,
        public string  $status,        // 'draft' | 'published' | 'archived' | 'disabled'
        public string  $statusLabel,   // Etiqueta en español para la vista
        public ?string $publishedAt,   // Formateada; null si es borrador
        public string  $createdAt,     // Formateada: "15 Ene 2025"
        public string  $updatedAt,     // Formateada: "15 Ene 2025"
        public int     $viewsCount,
        public int     $commentsCount,
        public ?string $coverUrl,      // null si no hay imagen de portada
        public int     $imagesCount,
        public int     $videosCount,
        public int     $filesCount,
    ) {}

    // ════════════════════════════════════════════════════════════════════
    //  FACTORY — construye el DTO desde un modelo Eloquent cargado
    // ════════════════════════════════════════════════════════════════════

    /**
     * Transforma un modelo News (con relaciones precargadas) en DTO.
     *
     * El modelo debe llegar con eager loading de:
     *  - 'media'             → para portada y conteo de adjuntos por tipo
     *  - 'category:id,name'  → para nombre de categoría
     *  - comments_count      → cargado con withCount('comments')
     *
     * SEGURIDAD XSS:
     *  - La protección XSS se delega exclusivamente a Blade mediante {{ }},
     *    que aplica htmlspecialchars() en cada punto de salida en la vista.
     *  - mb_substr() limita longitud máxima → evita strings gigantes en memoria.
     */
    public static function fromModel(News $news): self
    {
        // ── Portada: prioridad a collection='cover', fallback a primera imagen
        // El fallback preserva compatibilidad con noticias creadas antes de la migración
        $cover = $news->media->firstWhere('collection', 'cover')
            ?? $news->media->filter(fn ($m) => $m->isImage())->first();

        // ── Clasificar media del slider y documentos por colección (con fallback a MIME)
        // Si collection es null (registros legacy), se clasifica por tipo MIME
        $images = $news->media->filter(
            fn ($m) => $m->collection === 'slider'
                ? $m->isImage()
                : ($m->collection === null && $m->isImage() && $m !== $cover)
        );
        $videos = $news->media->filter(
            fn ($m) => $m->collection === 'slider'
                ? $m->isVideo()
                : ($m->collection === null && $m->isVideo())
        );
        $files  = $news->media->filter(
            fn ($m) => $m->collection === 'document'
                || ($m->collection === null && $m->isDocument())
        );

        return new self(
            id:            $news->id,
            uuid:          $news->uuid,
            title:         self::sanitize($news->title, 220),
            summary:       self::sanitize($news->summary ?? '', 300),
            authorName:    self::sanitize($news->author_name ?? '', 150),
            categoryName:  self::sanitize($news->category?->name ?? '—', 120),
            status:        $news->status,
            statusLabel:   self::translateStatus($news->status),
            publishedAt:   $news->published_at?->translatedFormat('d M Y'),
            createdAt:     $news->created_at->translatedFormat('d M Y'),
            updatedAt:     $news->updated_at->translatedFormat('d M Y'),
            viewsCount:    (int) ($news->views_count ?? 0),
            commentsCount: (int) ($news->comments_count ?? 0),
            coverUrl:      $cover?->url(),
            imagesCount:   $images->count(),
            videosCount:   $videos->count(),
            filesCount:    $files->count(),
        );
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    /** Traduce el estado de BD al español para mostrar en la interfaz */
    private static function translateStatus(string $status): string
    {
        return match ($status) {
            'published' => 'Publicada',
            'scheduled' => 'Programada',
            'draft'     => 'Borrador',
            'archived'  => 'Descontinuada',
            'disabled'  => 'Deshabilitada',
            default     => ucfirst($status),
        };
    }

    /**
     * Limita longitud máxima y normaliza espacios.
     * XSS prevenido por Blade con {{ }} en cada punto de salida de la vista.
     */
    private static function sanitize(string $value, int $maxLength): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }
}
