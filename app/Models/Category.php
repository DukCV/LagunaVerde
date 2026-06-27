<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type'];

    // Valores válidos del enum (útil para validaciones en FormRequest)
    public const TYPES = ['news', 'events', 'projects'];

    // ── Relaciones ───────────────────────────────────────────────────────

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeForEvents($query)
    {
        return $query->where('type', 'events');
    }

    public function scopeForNews($query)
    {
        return $query->where('type', 'news');
    }

    public function scopeForProjects($query)
    {
        return $query->where('type', 'projects');
    }
}
