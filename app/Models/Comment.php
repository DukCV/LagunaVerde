<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'body',
    ];

    // ── Relación polimórfica ─────────────────────────────────────────────

    /** Devuelve el modelo al que pertenece el comentario (Event, News, Project…) */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Relaciones normales ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
