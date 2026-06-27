<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila de la tabla 'event_partner': un colaborador invitado a un evento.
 *
 * Modelo dedicado (no un pivot implícito de belongsToMany) porque almacena
 * datos propios (orden, detalles de participación) y debe poder representar
 * colaboradores externos sin registro en 'partners' (is_custom = true,
 * partner_id NULL). Un belongsToMany estándar nunca devolvería esas filas,
 * porque su JOIN exige partner_id = partners.id — ver el docblock de la
 * migración 2026_06_21_000000_create_event_partner_table.
 */
class EventCollaborator extends Model
{
    protected $table = 'event_partner';

    protected $fillable = [
        'event_id',
        'partner_id',
        'is_custom',
        'custom_name',
        'custom_logo_path',
        'participation_details',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_custom' => 'boolean',
            'order'     => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Nulo cuando is_custom = true (colaborador externo, sin registro en 'partners'). */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
