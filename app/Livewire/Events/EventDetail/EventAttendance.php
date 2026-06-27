<?php

namespace App\Livewire\Events\EventDetail;

use App\Services\Events\EventAttendanceService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: confirmar/cancelar asistencia a un evento.
 *
 * UI dirigida por estado, sin formulario — ver event-attendance.blade.php:
 *  invitado | disponible | inscrito | cupo lleno | evento iniciado/finalizado.
 *
 * SEGURIDAD:
 *  - #[Locked] en $eventUuid → sin tampering del cliente.
 *  - toggleAttendance() revalida TODO en servidor (auth, horario, cupo) —
 *    nunca confía en el estado ya mostrado en el navegador.
 *  - Rate limiting + bloqueo pesimista en EventAttendanceService.
 */
class EventAttendance extends Component
{
    #[Locked]
    public string $eventUuid = '';

    // ── Estado calculado en mount()/cargarEstado() — solo lectura en la vista ──
    public int  $eventId          = 0;
    public int  $capacidadTotal   = 0;
    public int  $ocupados         = 0;
    public bool $estaInscrito     = false;
    public bool $eventoIniciado   = false;
    public bool $eventoFinalizado = false;

    /** Resultado de la última acción (éxito o error) */
    public string $mensaje        = '';
    public bool   $mensajeEsError = false;

    /** Inyectado vía boot() — Livewire 4 no inyecta en el constructor */
    private EventAttendanceService $service;

    public function boot(EventAttendanceService $service): void
    {
        $this->service = $service;
    }

    public function mount(string $eventUuid): void
    {
        $this->eventUuid = $eventUuid;
        $this->cargarEstado();
    }

    /** Abre el modal de login — mismo evento que escucha LoginModal */
    public function abrirLogin(): void
    {
        $this->dispatch('abrir-modal-login');
    }

    /** Abre el modal de registro — mismo evento que escucha RegisterModal */
    public function abrirRegistro(): void
    {
        $this->dispatch('abrir-modal-registro');
    }

    /** Un solo botón con doble función: confirma o cancela según el estado real */
    public function toggleAttendance(): void
    {
        if (! auth()->check()) {
            // Defensa en profundidad: el botón no debería ni renderizarse para invitados
            abort(403);
        }

        $resultado = $this->service->alternarAsistencia($this->eventId, auth()->id());

        $this->mensaje        = $resultado['message'];
        $this->mensajeEsError = ! $resultado['success'];

        // Relee el estado real desde la BD — nunca se asume el resultado
        $this->cargarEstado();
    }

    private function cargarEstado(): void
    {
        $datos = $this->service->obtenerEstado($this->eventUuid, auth()->id());

        $this->eventId          = $datos['eventId'];
        $this->capacidadTotal   = $datos['capacidadTotal'];
        $this->ocupados         = $datos['ocupados'];
        $this->estaInscrito     = $datos['estaInscrito'];
        $this->eventoIniciado   = $datos['eventoIniciado'];
        $this->eventoFinalizado = $datos['eventoFinalizado'];
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.events.event-detail.event-attendance');
    }
}
