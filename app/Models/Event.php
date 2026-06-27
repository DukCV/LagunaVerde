<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'published', 'cancelled', 'closed'];

    // Modalidades válidas del evento — usadas por AdminEventsForm (LocationForm)
    public const MODALITIES = ['presencial', 'virtual', 'hibrido'];

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'start_at',
        'end_at',
        'capacity_total',
        'content',
        'status',
        'published_at',
        'modality',
        'location',
        'virtual_link',
        'registration_enabled',
        'registration_start_at',
        'registration_end_at',
        'registration_no_end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_at'                  => 'datetime',
            'end_at'                    => 'datetime',
            'capacity_total'            => 'integer',
            'published_at'              => 'datetime',
            'registration_enabled'      => 'boolean',
            'registration_start_at'     => 'datetime',
            'registration_end_at'       => 'datetime',
            'registration_no_end_date'  => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * Vista de solo lectura sobre event_registrations para conteos y
     * comprobaciones eficientes (withCount/withExists) — ver
     * App\Services\Events\EventAttendanceService. Las escrituras NO pasan
     * por aquí: usan EventRegistration directamente ('cancelled' se
     * conserva como historial, la fila nunca se borra).
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->wherePivotIn('status', ['registered', 'waitlist']);
    }

    /** Media adjunta (polimórfica) */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    /** Comentarios (polimórfico) */
    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    /**
     * Colaboradores invitados a este evento (de BD o externos) — ver
     * App\Models\EventCollaborator. HasMany en vez de BelongsToMany: las
     * filas externas (is_custom = true) no tienen partner_id, y un
     * belongsToMany nunca las devolvería.
     */
    public function collaborators(): HasMany
    {
        return $this->hasMany(EventCollaborator::class)->orderBy('order');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_at', '>=', now());
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Plazas disponibles (considera sólo registros activos) */
    public function availableSpots(): int
    {
        $taken = $this->registrations()
                      ->whereIn('status', ['registered', 'waitlist'])
                      ->count();
        return max(0, $this->capacity_total - $taken);
    }

    public function isFull(): bool
    {
        return $this->availableSpots() === 0;
    }

    /**
     * Porcentaje de ocupación dado un número de inscritos ya calculado.
     * Recibe $registered como parámetro (en vez de consultar la relación)
     * para que las vistas administrativas puedan reutilizar un conteo
     * ya obtenido vía withCount() sin disparar una consulta adicional.
     */
    public function occupancyPercent(int $registered): int
    {
        return $this->capacity_total > 0
            ? (int) min(100, round(($registered / $this->capacity_total) * 100))
            : 0;
    }

    /**
     * Fecha límite efectiva de inscripción.
     * Si "sin fecha de fin" está activado, se usa la fecha de inicio del
     * evento como límite (regla de negocio del formulario de eventos).
     */
    public function effectiveRegistrationDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->registration_no_end_date
            ? $this->start_at
            : $this->registration_end_at;
    }
}
