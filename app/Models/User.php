<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // ── Asignación masiva ────────────────────────────────────────────────────
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'age',
        'interest_area',
        'state',
        'country',
        'profile_photo_path', // Ruta relativa de la foto de perfil en disco 'public'
        'active',            // Estado de la cuenta: false = inhabilitada por un administrador
    ];

    // ── Atributos ocultos en serialización ───────────────────────────────────
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ── Casting de tipos ─────────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'age'               => 'integer',
            'active'            => 'boolean',
        ];
    }

    // ── Boot: auto-generar UUID en la creación ───────────────────────────────
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────────

    /**
     * Roles asignados al usuario (relación N-N).
     * ->using(RoleUser::class) + ->withPivot(): el pivote también guarda
     * 'position', 'permissions', 'public_bio', 'social_links' y
     * 'show_in_about_us', propios de esta asignación de rol concreta
     * (ver App\Models\RoleUser).
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
                    ->using(RoleUser::class)
                    ->withPivot(['position', 'permissions', 'public_bio', 'social_links', 'show_in_about_us'])
                    ->withTimestamps();
    }

    /** Perfil de socio colaborador vinculado a esta cuenta (si el rol es 'Colaborador') */
    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /** Registros a eventos realizados por el usuario */
    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** Donaciones realizadas por el usuario */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** Comentarios escritos por el usuario */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** Noticias creadas por el usuario como autor */
    public function news(): HasMany
    {
        return $this->hasMany(News::class, 'author_id');
    }

    // ── Helpers de autorización ──────────────────────────────────────────────

    /**
     * Comprueba si el usuario posee un rol específico.
     * Optimizado: si los roles ya están cargados en memoria usa la colección;
     * si no, lanza una sola consulta con índice en 'name'.
     */
    public function hasRole(string $roleName): bool
    {
        // Evita una consulta extra si la relación ya fue precargada (eager loaded)
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains('name', $roleName);
        }

        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Alias semántico: comprueba si el usuario es Administrador.
     * Centraliza el magic string del nombre del rol en un solo lugar.
     */
    public function isAdministrator(): bool
    {
        return $this->hasRole('Administrador');
    }

    /**
     * Comprueba si el usuario tiene un permiso granular concreto, leído desde
     * el pivote de su(s) asignación(es) de rol (role_user.permissions).
     *
     * NOTA DE ALCANCE: este helper solo CONSULTA el permiso almacenado — no
     * existe todavía ningún punto del panel que LO EXIJA (gate/middleware).
     * La aplicación real de estos permisos en cada módulo administrativo
     * queda fuera del alcance de esta iteración (ver AdminRoleService).
     */
    public function hasPermission(string $key): bool
    {
        if (! $this->relationLoaded('roles')) {
            $this->load('roles');
        }

        return $this->roles->contains(
            fn (Role $role) => in_array($key, $role->pivot->permissions ?? [], true)
        );
    }

    // ── Helpers de perfil ────────────────────────────────────────────────────

    /**
     * Devuelve la URL pública de la foto de perfil.
     * Retorna null si no existe foto (el header usará el badge de iniciales).
     *
     * Usa Storage::url() que es compatible con discos 'public' en Hostinger.
     */
    public function profilePhotoUrl(): ?string
    {
        if (! $this->profile_photo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    /**
     * Extrae las iniciales del nombre completo del usuario.
     * Ejemplos:
     *   'Juan Pérez'              → 'JP'
     *   'Ana'                     → 'A'
     *   'Administrador Laguna V.' → 'AL'
     *
     * Máximo 2 iniciales para que quepan en el badge circular.
     */
    public function getInitials(): string
    {
        // Divide el nombre en palabras y extrae la primera letra de cada una
        $words = array_filter(explode(' ', trim($this->name)));

        $initials = array_map(
            fn(string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2) // Solo las dos primeras palabras
        );

        return implode('', $initials) ?: '?';
    }
}
