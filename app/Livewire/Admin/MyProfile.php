<?php

namespace App\Livewire\Admin;

use App\Services\Admin\AdminRoleService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Componente Livewire: "Mi Perfil" del Panel de Administración.
 *
 * SEGURIDAD CRÍTICA (IDOR): este componente SOLO opera sobre auth()->id().
 * A diferencia de UserRoleManager (que administra el rol de OTRO usuario
 * recibiendo su ID como parámetro), aquí NO existe ningún parámetro de ID
 * que el cliente pueda manipular — ni en mount() ni en ninguna acción. Esto
 * elimina por diseño cualquier posibilidad de ver o modificar el perfil de
 * otra persona a través de este componente.
 *
 * ALCANCE DE ESTA ITERACIÓN:
 *  "Editar perfil", "Inhabilitar cuenta" y "Cambiar contraseña" son
 *  marcadores visuales inactivos a propósito (ver my-profile.blade.php) —
 *  sin lógica de mutación todavía. El interruptor "Mostrar mi perfil en
 *  Quiénes Somos" SÍ es funcional: alterna role_user.show_in_about_us para
 *  la asignación de rol del usuario autenticado (ver
 *  AdminRoleService::toggleVisibilidadPublica()).
 *
 * SEGURIDAD:
 *  - autorizarAcceso() en mount() y en la acción de alternar visibilidad.
 *  - Rate limiting por usuario en el interruptor — acción de bajo riesgo,
 *    pero igualmente sujeta a un límite básico ante clics/scripts repetidos.
 *  - Toda salida usa {{ }} en Blade → escape XSS automático garantizado.
 */
class MyProfile extends Component
{
    public string $nombre            = '';
    public string $correo            = '';
    public ?string $fotoUrl          = null;
    public string $iniciales         = '';
    public string $puesto            = '';
    public string $semblanzaPublica  = '';

    /** @var array<string, string|null> */
    public array $redesSociales      = [];

    public bool $mostrarEnQuienesSomos = false;

    private const RL_VISIBILIDAD_MAX   = 20;
    private const RL_VISIBILIDAD_DECAY = 60;

    public function mount(AdminRoleService $service): void
    {
        $this->autorizarAcceso();

        $datos = $service->getMyProfileData(auth()->id());

        $this->nombre                = $datos['nombre'];
        $this->correo                = $datos['correo'];
        $this->fotoUrl                = $datos['fotoUrl'];
        $this->iniciales             = $datos['iniciales'];
        $this->puesto                = $datos['puesto'];
        $this->semblanzaPublica      = $datos['semblanzaPublica'];
        $this->redesSociales         = $datos['redesSociales'];
        $this->mostrarEnQuienesSomos = $datos['mostrarEnQuienesSomos'];
    }

    /**
     * Alterna la visibilidad pública del PROPIO perfil. Siempre opera sobre
     * auth()->id() — jamás sobre un ID recibido del cliente.
     */
    public function alternarVisibilidadPublica(AdminRoleService $service): void
    {
        $this->autorizarAcceso();

        $clave = 'mi-perfil-visibilidad:' . auth()->id();
        if (RateLimiter::tooManyAttempts($clave, self::RL_VISIBILIDAD_MAX)) {
            $this->dispatch('notificacion', tipo: 'error', mensaje: 'Demasiados cambios seguidos. Espera un momento e inténtalo de nuevo.');
            return;
        }

        RateLimiter::hit($clave, self::RL_VISIBILIDAD_DECAY);

        $this->mostrarEnQuienesSomos = $service->toggleVisibilidadPublica(auth()->id());

        $mensaje = $this->mostrarEnQuienesSomos
            ? 'Tu perfil ahora se muestra en "Quiénes Somos".'
            : 'Tu perfil ya no se muestra en "Quiénes Somos".';

        $this->dispatch('notificacion', tipo: 'exito', mensaje: $mensaje);
    }

    public function render()
    {
        return view('livewire.admin.my-profile');
    }

    /** Verifica que haya una sesión autenticada — defensa en profundidad. */
    private function autorizarAcceso(): void
    {
        if (! auth()->check()) {
            abort(403, 'Acceso no autorizado.');
        }
    }
}
