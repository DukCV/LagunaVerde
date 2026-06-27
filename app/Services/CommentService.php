<?php

namespace App\Services;

use App\DTOs\CommentDto;
use App\Repositories\CommentRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CommentService
{
    // TTL alineado con wire:poll.15s → el caché nunca devuelve datos
    // con más de 15 segundos de antigüedad entre refrescos del componente.
    private const CACHE_TTL_SECONDS = 15;

    public function __construct(
        private readonly CommentRepository $repository
    ) {}

    /**
     * Devuelve los comentarios de una noticia, usando caché de corto plazo
     * para absorber las peticiones del wire:poll sin saturar la BD.
     *
     * @return CommentDto[]
     */
    public function getForNews(int $newsId): array
    {
        return Cache::remember(
            key:      $this->cacheKey($newsId),
            ttl:      self::CACHE_TTL_SECONDS,
            callback: fn () => $this->repository
                ->forNews($newsId)
                ->map(fn ($c) => CommentDto::fromModel($c))
                ->all(),
        );
    }

    /**
     * Persiste un comentario y elimina el caché de la noticia para que
     * el próximo poll recupere datos frescos de la BD.
     */
    public function post(int $newsId, int $userId, string $body): CommentDto
    {
        // Segunda capa de autenticación — la primera ocurre en el componente
        if (! Auth::check() || Auth::id() !== $userId) {
            abort(403);
        }

        if ($this->repository->userCommentedRecently($newsId, $userId)) {
            throw ValidationException::withMessages([
                'body' => 'Por favor espera un momento antes de comentar de nuevo.',
            ]);
        }

        $cleanBody = mb_substr(strip_tags(trim($body)), 0, 1_000);

        $comment = $this->repository->create($newsId, $userId, $cleanBody);
        $comment->load('user:id,name');

        // Invalidar caché para que los demás usuarios vean el comentario nuevo
        Cache::forget($this->cacheKey($newsId));

        return CommentDto::fromModel($comment);
    }

    /** Clave de caché con espacio de nombres para evitar colisiones entre módulos. */
    private function cacheKey(int $newsId): string
    {
        return "comments.news.{$newsId}";
    }
}
