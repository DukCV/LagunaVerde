<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SERVICIO: Confirmar/cancelar asistencia a un evento
//
//  Persiste sobre la tabla 'event_registrations' YA EXISTENTE (ver
//  App\Models\EventRegistration) — no se crea una tabla pivote nueva.
//  'cancelled' es un estado, nunca se borra la fila (conserva historial).
//
//  Seguridad:
//    - Rate limiting por usuario+evento — mitiga spam del botón (DoS).
//    - Bloqueo pesimista (lockForUpdate) sobre la fila del evento dentro de
//      una transacción: serializa intentos concurrentes del MISMO evento,
//      así el conteo de ocupación que se lee a continuación es confiable
//      (evita sobrecupo si dos usuarios confirman en el mismo instante).
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Services\Events;

use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class EventAttendanceService
{
    // Estados que ocupan un lugar del aforo
    private const ESTADOS_ACTIVOS = ['registered', 'waitlist'];

    private const MAX_INTENTOS     = 10;
    private const SEGUNDOS_BLOQUEO = 60;

    /**
     * Estado de asistencia para la vista — UNA sola consulta
     * (withCount + withExists), sin cargar colecciones de filas.
     *
     * @return array{eventId:int, capacidadTotal:int, ocupados:int, estaInscrito:bool, eventoIniciado:bool, eventoFinalizado:bool}
     */
    public function obtenerEstado(string $eventUuid, ?int $userId): array
    {
        $event = Event::query()
            ->select(['id', 'start_at', 'end_at', 'capacity_total'])
            ->where('uuid', $eventUuid)
            ->where('status', 'published')
            ->withCount('attendees')
            ->when($userId !== null, fn ($q) => $q->withExists([
                'attendees as esta_inscrito' => fn ($q2) => $q2->where('users.id', $userId),
            ]))
            ->firstOrFail();

        return [
            'eventId'          => $event->id,
            'capacidadTotal'   => $event->capacity_total,
            'ocupados'         => $event->attendees_count,
            'estaInscrito'     => (bool) ($event->esta_inscrito ?? false),
            'eventoIniciado'   => $event->start_at->isPast(),
            'eventoFinalizado' => $event->end_at !== null && $event->end_at->isPast(),
        ];
    }

    /**
     * Confirma o cancela según el estado ACTUAL en BD (nunca el del cliente).
     *
     * @return array{success:bool, message:string, accion?:string}
     */
    public function alternarAsistencia(int $eventId, int $userId): array
    {
        $clave = 'asistencia_evento|' . $userId . '|' . $eventId;

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);

            return [
                'success' => false,
                'message' => "Demasiados intentos. Espera {$segundos} segundo(s).",
            ];
        }

        RateLimiter::hit($clave, self::SEGUNDOS_BLOQUEO);

        return DB::transaction(function () use ($eventId, $userId) {
            // Bloquea la fila del evento: serializa confirmaciones concurrentes
            $event = Event::where('id', $eventId)->lockForUpdate()->firstOrFail();

            if ($event->start_at->isPast()) {
                return ['success' => false, 'message' => 'El evento ya comenzó. No se permiten cambios.'];
            }

            $registro = EventRegistration::where('event_id', $eventId)
                ->where('user_id', $userId)
                ->first();

            $estaInscrito = $registro !== null
                && in_array($registro->status, self::ESTADOS_ACTIVOS, true);

            // ── Cancelar ──────────────────────────────────────────────────
            if ($estaInscrito) {
                $registro->update(['status' => 'cancelled']);

                return ['success' => true, 'accion' => 'cancelado', 'message' => 'Has cancelado tu asistencia.'];
            }

            // ── Confirmar: re-verifica el cupo dentro del bloqueo ────────
            $ocupados = EventRegistration::where('event_id', $eventId)
                ->whereIn('status', self::ESTADOS_ACTIVOS)
                ->count();

            if ($ocupados >= $event->capacity_total) {
                return ['success' => false, 'message' => 'El cupo de este evento ya se llenó.'];
            }

            // updateOrCreate respeta el unique(event_id,user_id): reactiva
            // una fila 'cancelled' previa en vez de duplicarla.
            EventRegistration::updateOrCreate(
                ['event_id' => $eventId, 'user_id' => $userId],
                ['status' => 'registered', 'registered_at' => now()],
            );

            return ['success' => true, 'accion' => 'confirmado', 'message' => '¡Asistencia confirmada!'];
        });
    }
}
