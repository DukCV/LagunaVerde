<?php

namespace App\Models;

use App\Models\Scopes\PublishedScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use Tonysm\RichTextLaravel\Models\Traits\HasRichText;

class News extends Model
{
    use HasFactory, HasRichText;

    public const STATUSES = ['draft', 'published', 'archived', 'disabled', 'scheduled'];

    /**
     * 'content' se gestiona como Rich Text (Trix) en modo "attribute": el cast
     * AsRichTextContent se aplica directamente sobre la columna 'content' ya
     * existente (longText), sin crear una tabla polimórfica nueva.
     *
     * Acceder a $news->content devuelve un objeto Tonysm\RichTextLaravel\Content;
     * usar ->toHtml() para obtener el fragmento HTML "crudo" (sin wrapper) o
     * (string) para el HTML envuelto en <div class="trix-content">.
     */
    protected $richTextAttributes = [
        'content' => ['attribute' => true],
    ];

    protected $fillable = [
        'title',
        'summary',
        'author_name',
        'author_id',           // FK al usuario que creó la noticia
        'category_id',
        'published_at',
        'first_published_at',  // Marca inmutable de primera publicación; NULL si nunca publicada
        'content',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'published_at'       => 'datetime',
            'first_published_at' => 'datetime', // Inmutable; null = nunca publicada
            'views_count'        => 'integer',  // Contador atómico, se incrementa con DB::increment()
        ];
    }

    /**
     * Acciones de arranque del modelo.
     *
     * Se usa boot() para registrar el listener de creación del UUID.
     * El Global Scope se registra en booted() para garantizar que Eloquent
     * ya haya inicializado completamente el modelo antes de añadirlo.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Generar UUID automáticamente al crear una nueva noticia.
        // Esto garantiza que el identificador público esté siempre presente
        // desde el primer instante, incluso en inserciones directas via factory.
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Registra el Global Scope de visibilidad pública.
     *
     * booted() se ejecuta una vez por ciclo de vida del modelo, después de boot().
     * Es el lugar correcto para registrar Global Scopes según las convenciones de Laravel.
     *
     * EFECTO: toda consulta que parta de News:: en el lado público
     * filtrará automáticamente por status='published' AND published_at<=now().
     *
     * PARA DESACTIVARLO en el panel de administración:
     *   News::withoutGlobalScope(PublishedScope::class)->where(...);
     * 
     *   Usar explícitamente withoutGlobalScope() en el repositorio de administración
     *   respeta los principios de Clean Code al hacer la intención del código
     *   completamente evidente al lector.
     */
    protected static function booted(): void
    {
        // Registrar la red de seguridad de visibilidad pública.
        // Este scope se aplica en CADA query de News:: salvo opt-out explícito.
        static::addGlobalScope(new PublishedScope());
    }

    // ── Route model binding por UUID ─────────────────────────────────────
    // /noticias/{uuid} nunca expone el ID autoincremental.
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // Autor de la noticia — puede ser null en registros anteriores a la migración
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /**
     * Local scope: filtra explícitamente por noticias publicadas.
     *
     * PROPÓSITO:
     *  Aunque el Global Scope (PublishedScope) ya aplica estas mismas
     *  condiciones de forma automática, este local scope se mantiene por
     *  dos razones:
     *   1. LEGIBILIDAD: News::published()->... comunica la intención de forma
     *      explícita y actúa como documentación viva del filtro público.
     *   2. REDUNDANCIA SEGURA: en contextos donde se desactiva el Global Scope
     *      (p.ej. dentro de whereHas en repositorios), llamar ->published()
     *      garantiza que el filtro se aplique igualmente.
     *
     * CONDICIONES:
     *  - status = 'published'       → excluye draft, archived, disabled.
     *  - published_at IS NOT NULL   → excluye noticias sin fecha asignada.
     *  - published_at <= now()      → excluye publicaciones programadas a futuro.
     *
     * @param  Builder $query  El constructor de consulta Eloquent.
     * @return Builder         El builder con los filtros de publicación aplicados.
     */
    public function scopePublished(Builder $query): Builder
    {
        // Filtrar únicamente noticias en estado 'published'.
        return $query->where('status', 'published')
                     // Excluir registros sin fecha de publicación definida.
                     ->whereNotNull('published_at')
                     // Excluir publicaciones cuya fecha aún no ha llegado.
                     ->where('published_at', '<=', now());
    }
}
