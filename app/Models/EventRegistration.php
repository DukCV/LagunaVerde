<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistration extends Model
{
    use HasFactory;

    public const STATUSES = ['registered', 'waitlist', 'cancelled', 'attended', 'no_show'];

    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'registered_at',
        'checked_in_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['registered', 'waitlist']);
    }

    public function scopeAttended($query)
    {
        return $query->where('status', 'attended');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Marca asistencia y guarda la hora actual */
    public function checkIn(): bool
    {
        if ($this->status !== 'registered') {
            return false;
        }
        return $this->update([
            'status'        => 'attended',
            'checked_in_at' => now(),
        ]);
    }
}
