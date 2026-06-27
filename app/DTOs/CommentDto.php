<?php

namespace App\DTOs;

use App\Models\Comment;

/**
 * DTO inmutable para un comentario de noticia.
 *
 * Archivo separado de SidebarNewsDto para cumplir PSR-4:
 * un archivo = una clase, nombre de archivo = nombre de clase.
 *
 * SEGURIDAD:
 *  - El nombre del autor viene del modelo User autenticado, no del input.
 *  - El body pasa por strip_tags() → sin posibilidad de XSS.
 *  - El email del usuario NO se expone en ningún campo del DTO.
 */
readonly class CommentDto
{
    public function __construct(
        public int    $id,
        public string $authorName,
        public string $body,
        public string $publishedAt,    // "15 Ene 2025 · 14:32"
        public string $publishedAtIso, // para <time datetime="">
    ) {}

    public static function fromModel(Comment $comment): self
    {
        return new self(
            id:             $comment->id,
            authorName:     mb_substr(strip_tags($comment->user->name ?? 'Anónimo'), 0, 150),
            body:           mb_substr(strip_tags($comment->body), 0, 1_000),
            publishedAt:    $comment->created_at->translatedFormat('d M Y · H:i'),
            publishedAtIso: $comment->created_at->toIso8601String(),
        );
    }
}
