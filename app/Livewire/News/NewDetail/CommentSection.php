<?php

namespace App\Livewire\News\NewDetail;

use App\DTOs\CommentDto;
use App\Models\News;
use App\Services\CommentService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Componente Livewire: sección de comentarios con actualización en tiempo real.
 *
 * CORRECCIÓN CRÍTICA (bug de alcance de datos):
 *  $newsId ahora es public + #[Locked]. Antes era `private` y Livewire solo
 *  serializa propiedades públicas entre peticiones: en cada wire:poll y en
 *  submitComment el valor se restablecía a 0, ejecutando getForNews(0)
 *  y guardando comentarios con commentable_id=0 (desvinculados del artículo).
 *
 * SEGURIDAD:
 *  - #[Locked] en $newsUuid y $newsId → sin tampering del cliente.
 *  - Rate limiting por usuario: máx. 5 comentarios/minuto via RateLimiter.
 *  - Anti-spam en CommentService (ventana de 1 min en BD).
 *  - El servicio re-verifica auth()->id() como segunda capa de defensa.
 *  - El email del usuario NUNCA se muestra en los DTOs retornados.
 */
class CommentSection extends Component
{
    /** UUID público de la noticia — identificador de ruta, nunca el ID entero. */
    #[Locked]
    public string $newsUuid = '';

    /**
     * ID interno resuelto una vez en mount().
     * #[Locked] lo persiste entre re-hidrataciones Y bloquea escritura del cliente.
     */
    #[Locked]
    public int $newsId = 0;

    // ── Campos del formulario ────────────────────────────────────────────

    #[Rule('required|string|min:10|max:1000')]
    public string $body = '';

    // ── Estado de UI ─────────────────────────────────────────────────────

    public bool $showSuccess = false;

    /**
     * Comentarios serializados como arrays (CommentDto no implementa Wireable
     * para mantenerlo simple; se serializa manualmente).
     * @var array[]
     */
    public array $comments = [];

    // ════════════════════════════════════════════════════════════════════
    //  LIFECYCLE
    // ════════════════════════════════════════════════════════════════════

    public function mount(string $newsUuid, CommentService $service): void
    {
        $this->newsUuid = $newsUuid;

        // Resolver UUID → ID interno una sola vez en mount().
        // $newsId queda persistido en el estado Livewire gracias a #[Locked].
        $news = News::published()
            ->select(['id'])
            ->where('uuid', $newsUuid)
            ->firstOrFail();

        $this->newsId = $news->id;
        $this->loadComments($service);
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES
    // ════════════════════════════════════════════════════════════════════

    /** Recarga comentarios — invocado por wire:poll. */
    public function refreshComments(CommentService $service): void
    {
        $this->loadComments($service);
    }

    /**
     * Persiste el comentario del usuario autenticado.
     *
     * Capas de seguridad en orden:
     *  1. Auth::check()   → rechaza no autenticados.
     *  2. RateLimiter     → máx. 5 intentos/minuto por usuario.
     *  3. $this->validate → valida longitud y tipo del body.
     *  4. CommentService  → re-verifica auth + anti-spam en BD.
     */
    public function submitComment(CommentService $service): void
    {
        // Capa 1: autenticación obligatoria
        if (! Auth::check()) {
            $this->addError('body', 'Debes iniciar sesión para comentar.');
            return;
        }

        // Capa 2: rate limiting (5 por minuto por usuario)
        $rateLimitKey = 'comment:' . Auth::id();
        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $this->addError('body', "Demasiados intentos. Espera {$seconds} segundos.");
            return;
        }
        RateLimiter::hit($rateLimitKey, decaySeconds: 60);

        // Capa 3: validación de campos
        $this->validate();

        try {
            // Capa 4: servicio — re-verifica auth + anti-spam + sanitiza
            $dto = $service->post(
                newsId: $this->newsId,
                userId: Auth::id(),
                body:   $this->body,
            );

            // Insertar el nuevo comentario al principio de la lista
            array_unshift($this->comments, $this->commentToArray($dto));

            $this->reset('body');
            $this->showSuccess = true;

        } catch (ValidationException $e) {
            $this->addError('body', $e->errors()['body'][0] ?? 'Error al publicar el comentario.');
        } catch (\Throwable) {
            // No revelar detalles del error interno
            $this->addError('body', 'No fue posible publicar el comentario. Intenta de nuevo.');
        }
    }

    public function hideSuccess(): void
    {
        $this->showSuccess = false;
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.news.new-detail.comment-section');
    }

    // ════════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    private function loadComments(CommentService $service): void
    {
        // Sintaxis callable (PHP 8.1+) — elimina la lambda innecesaria
        $this->comments = array_map(
            $this->commentToArray(...),
            $service->getForNews($this->newsId)
        );
    }

    /** Convierte un CommentDto en array primitivo para serialización Livewire. */
    private function commentToArray(CommentDto $dto): array
    {
        return [
            'id'             => $dto->id,
            'authorName'     => $dto->authorName,
            'body'           => $dto->body,
            'publishedAt'    => $dto->publishedAt,
            'publishedAtIso' => $dto->publishedAtIso,
        ];
    }
}
