<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // ── Relaciones ───────────────────────────────────────────────────────

    /**
     * Usuarios que poseen este rol.
     * ->using(RoleUser::class) + ->withPivot(): ver App\Models\RoleUser —
     * el pivote guarda 'position', 'permissions', 'public_bio',
     * 'social_links' y 'show_in_about_us' de cada asignación.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')
                    ->using(RoleUser::class)
                    ->withPivot(['position', 'permissions', 'public_bio', 'social_links', 'show_in_about_us'])
                    ->withTimestamps();
    }

    /** Permisos asignados al rol */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role');
    }
}
