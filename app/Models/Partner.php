<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Socio colaborador: empresa, ONG, fundación, institución educativa o
 * gubernamental que apoya al proyecto — O el perfil público de un usuario
 * con rol 'Colaborador' (ver 'user_id').
 *
 * user_id NULL  → socio público tradicional, sin cuenta de acceso.
 * user_id != NULL → perfil de socio vinculado a esa cuenta de usuario,
 *                    gestionado desde "Administrar rol" en Gestión de Usuarios.
 */
class Partner extends Model
{
    use HasFactory;

    // Lista blanca de tipos de organización admitidos. Se valida contra esta
    // constante en el formulario y alimenta el selector de filtro del listado.
    // String en BD (no enum) para poder añadir tipos nuevos sin migración.
    public const TYPES = [
        'Corporativo',
        'Educativo',
        'ONG',
        'Gubernamental',
        'Tecnológico',
        'Fundación',
        'Comunitario',
        'Persona',
    ];

    protected $fillable = [
        'user_id', // Nullable: solo presente cuando el socio es el perfil de un usuario
        'name',
        'type',
        'active',
        'website',
        'social_instagram',
        'social_facebook',
        'social_twitter',
        'social_linkedin',
        'social_youtube',
        'who_they_are',
        'how_they_support',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    /** Logotipo del socio: único registro con collection='logo' en la tabla polimórfica media */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Cuenta de usuario propietaria de este perfil (null en socios públicos tradicionales) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /** Filtra únicamente socios activos — usado en la sección pública del sitio. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
