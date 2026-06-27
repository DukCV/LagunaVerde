<?php

// ══════════════════════════════════════════════════════════════════════════════
//  COMPONENTE LIVEWIRE: Modal de inicio de sesión
//
//  Arquitectura:
//    - Escucha el evento 'abrir-modal-login' emitido por el Header
//    - Delega la lógica de autenticación a LoginService (SRP)
//    - Maneja el estado del modal y los errores de validación
//
//  Seguridad:
//    - Validación del lado servidor antes de intentar la autenticación
//    - Rate limiting aplicado en LoginService (defensa en profundidad)
//    - Regeneración de sesión tras login exitoso (prevención de session fixation)
//    - Los mensajes de error son genéricos para evitar enumeración de usuarios
//    - XSS: Livewire escapa automáticamente las propiedades en las vistas Blade
//
//  Compatibilidad Hostinger:
//    - No usa WebSockets ni canales broadcast (usa polling de Livewire)
//    - SESSION_DRIVER=file (ya configurado en .env)
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Livewire\Auth;

use App\Services\Auth\LoginService;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

class LoginModal extends Component
{
    // ── Estado del modal ─────────────────────────────────────────────────────

    /** Controla la visibilidad del modal en la vista */
    public bool $mostrarModal = false;

    // ── Campos del formulario ────────────────────────────────────────────────

    /** Correo electrónico ingresado por el usuario */
    #[Validate('required|email|max:190')]
    public string $email = '';

    /** Contraseña en texto plano (solo en memoria, nunca se persiste) */
    #[Validate('required|string|min:6|max:255')]
    public string $password = '';

    /** Opción de recordar la sesión (cookie persistente de larga duración) */
    public bool $recordarme = false;

    /** Mensaje de error de autenticación para mostrar al usuario */
    public string $mensajeError = '';

    /** Mensaje de éxito recibido al venir del registro (ver RegisterModal) */
    public string $mensajeExito = '';

    // ── Dependencias ─────────────────────────────────────────────────────────

    /**
     * Servicio de autenticación inyectado por el contenedor de Laravel.
     * No se declara como propiedad pública para que Livewire no lo serialice.
     */
    private LoginService $loginService;

    /**
     * Inyección de dependencias vía método boot (compatible con Livewire 4).
     * Livewire 4 soporta inyección en mount() pero no en el constructor.
     */
    public function boot(LoginService $loginService): void
    {
        $this->loginService = $loginService;
    }

    // ── Listeners de eventos ─────────────────────────────────────────────────

    /**
     * Abre el modal cuando el Header emite el evento 'abrir-modal-login'.
     * El atributo #[On] registra el listener de forma declarativa.
     */
    #[On('abrir-modal-login')]
    public function abrirModal(?string $mensaje = null): void
    {
        // Limpia el estado previo al abrir para una experiencia limpia
        $this->resetForm();
        $this->mostrarModal = true;
        // $mensaje llega desde RegisterModal tras un registro exitoso
        $this->mensajeExito = $mensaje ?? '';
    }

    // ── Acciones del componente ──────────────────────────────────────────────

    /**
     * Cierra el modal y limpia el formulario.
     * Se invoca con wire:click en el botón "Cerrar" y en el backdrop.
     */
    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetForm();
    }

    /**
     * Procesa el intento de inicio de sesión.
     *
     * Flujo:
     *  1. Valida los campos del formulario (Livewire #[Validate])
     *  2. Delega la autenticación a LoginService (rate limit + Auth::attempt)
     *  3. En éxito: regenera la sesión y recarga la página completa
     *  4. En fallo: muestra el mensaje de error correspondiente
     */
    public function login(): void
    {
        // Valida los campos usando las reglas definidas en #[Validate]
        $this->validate();

        // Obtiene la IP del cliente para el rate limiting
        $ip = request()->ip() ?? '0.0.0.0';

        // Delega la autenticación al servicio especializado
        $resultado = $this->loginService->intentarLogin(
            email:    $this->email,
            password: $this->password,
            remember: $this->recordarme,
            ip:       $ip,
        );

        if ($resultado['success']) {
            // Regenera el ID de sesión para prevenir session fixation attacks
            session()->regenerate();

            // Limpia el formulario por seguridad antes de redirigir
            $this->resetForm();
            $this->mostrarModal = false;

            // Recarga la página completa para que el Header actualice el estado
            // Livewire SPA navigation se encargará del resto
            $this->redirect(request()->header('Referer', '/'), navigate: true);

            return;
        }

        // Autenticación fallida: muestra el mensaje sin revelar detalles sensibles
        $this->mensajeError = $resultado['message'];
        $this->mensajeExito = ''; // Oculta un aviso de éxito previo, si lo había

        // Limpia solo la contraseña (mantiene el email para comodidad del usuario)
        $this->password = '';
    }

    // ── Métodos privados ─────────────────────────────────────────────────────

    /**
     * Resetea todos los campos del formulario a su estado inicial.
     * Centraliza el reset para no repetir código (principio DRY).
     */
    private function resetForm(): void
    {
        $this->email        = '';
        $this->password     = '';
        $this->recordarme   = false;
        $this->mensajeError = '';
        $this->mensajeExito = '';
        $this->resetValidation(); // Limpia los errores de validación de Livewire
    }

    // ── Renderizado ──────────────────────────────────────────────────────────

    public function render(): \Illuminate\View\View
    {
        return view('livewire.auth.login-modal');
    }
}
