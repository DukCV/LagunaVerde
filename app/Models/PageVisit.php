<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de registro de visitas al sitio público.
 *
 * Se crea un registro por cada petición GET de navegación normal
 * (excluye AJAX, JSON y rutas del panel de administración).
 * Usado por el TrafficChart del dashboard para mostrar métricas reales.
 */
class PageVisit extends Model
{
    // ── Asignación masiva permitida ──────────────────────────────────────
    protected $fillable = [
        'session_id',
        'ip_address',
        'url',
        'visited_at',
    ];

    // ── Casting de tipos ─────────────────────────────────────────────────
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
