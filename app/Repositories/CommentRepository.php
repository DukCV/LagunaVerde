<?php

namespace App\Repositories;

use App\Models\Comment;
use Illuminate\Support\Collection;

/**
 * Repositorio de comentarios polimórficos.
 *
 * SEGURIDAD:
 *  - Prepared statements en toda condición WHERE (Eloquent ORM).
 *  - select() explícito: el email del usuario NUNCA se retorna.
 *  - orderBy('created_at') determinista → sin manipulación del orden.
 */
class CommentRepository
{
    /** Límite de comentarios por consulta — previene cargas masivas en tablas grandes. */
    public const MAX_PER_PAGE = 50;

    /**
     * Comentarios recientes de una noticia con el nombre del autor.
     * El índice compuesto (commentable_type, commentable_id, created_at)
     * cubre el WHERE y el ORDER BY en un solo index scan.
     *
     * @param  int $newsId  ID interno (no expuesto al cliente).
     * @return Collection<int, Comment>
     */
    public function forNews(int $newsId): Collection
    {
        return Comment::where('commentable_type', 'news')
            ->where('commentable_id', $newsId)
            ->select(['id', 'user_id', 'body', 'created_at'])
            ->with('user:id,name')   // carga ansiosa — evita N+1; solo nombre, nunca email
            ->latest()
            ->limit(self::MAX_PER_PAGE)
            ->get();
    }

    /**
     * Persiste un nuevo comentario de un usuario autenticado.
     *
     * @param  int    $newsId   ID interno de la noticia.
     * @param  int    $userId   ID del usuario autenticado.
     * @param  string $body     Texto del comentario (ya validado en el servicio).
     */
    public function create(int $newsId, int $userId, string $body): Comment
    {
        return Comment::create([
            'commentable_type' => 'news',
            'commentable_id'   => $newsId,
            'user_id'          => $userId,
            'body'             => $body,
        ]);
    }

    /**
     * Verifica si un usuario ya comentó en esta noticia recientemente.
     * Usado para prevenir spam (ventana de 1 minuto).
     */
    public function userCommentedRecently(int $newsId, int $userId): bool
    {
        return Comment::where('commentable_type', 'news')
            ->where('commentable_id', $newsId)
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();
    }
}
