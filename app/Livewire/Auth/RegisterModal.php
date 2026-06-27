<?php

// ══════════════════════════════════════════════════════════════════════════════
//  COMPONENTE LIVEWIRE: Modal de registro de usuario
//
//  Arquitectura:
//    - Escucha 'abrir-modal-registro' emitido por el Header
//    - Delega la creación a RegisterService (SRP, igual que LoginModal/LoginService)
//    - Al crear la cuenta NO inicia sesión: emite 'abrir-modal-login' para que
//      LoginModal se abra con un mensaje de éxito (transición sin recargar)
//
//  Seguridad:
//    - Validación completa del lado servidor antes de tocar la BD
//    - Rate limiting por IP en RegisterService (defensa en profundidad)
//    - XSS: Livewire/Blade escapan automáticamente las propiedades en la vista
//
//  Nota sobre validación: no se usa el atributo #[Validate] porque la regla
//  de password requiere Password::default() (objeto, no string constante),
//  lo cual no es válido como argumento de atributo PHP. Se valida todo junto
//  en registrar() para no mezclar dos estilos de validación en el componente.
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Livewire\Auth;

use App\Services\Auth\RegisterService;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\On;
use Livewire\Component;

class RegisterModal extends Component
{
    /** Controla la visibilidad del modal */
    public bool $mostrarModal = false;

    public string $name = '';
    public string $email = '';

    /** Texto plano en memoria, nunca se persiste */
    public string $password = '';
    public string $password_confirmation = '';

    /** Mensaje de error no asociado a un campo (ej. bloqueo por rate limit) */
    public string $mensajeError = '';

    /** Inyectado vía boot() — Livewire 4 no inyecta en el constructor */
    private RegisterService $registerService;

    public function boot(RegisterService $registerService): void
    {
        $this->registerService = $registerService;
    }

    /** Abre el modal al recibir el evento del Header */
    #[On('abrir-modal-registro')]
    public function abrirModal(): void
    {
        $this->resetForm();
        $this->mostrarModal = true;
    }

    /** Cierra el modal y limpia el formulario */
    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetForm();
    }

    /**
     * Valida y crea la cuenta. En éxito: cierra este modal y abre el de
     * login con un mensaje de bienvenida — sin autenticar al usuario.
     */
    public function registrar(): void
    {
        $this->validate([
            'name'     => ['required', 'string', 'max:150'],
            'email'    => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::default()],
        ], [
            'email.unique' => 'Ya existe una cuenta registrada con este correo.',
        ]);

        $resultado = $this->registerService->registrar(
            name:     $this->name,
            email:    $this->email,
            password: $this->password,
            ip:       request()->ip() ?? '0.0.0.0',
        );

        if (! $resultado['success']) {
            $this->mensajeError = $resultado['message'];
            return;
        }

        $this->resetForm();
        $this->mostrarModal = false;

        // Mismo evento que escucha LoginModal (ver Header::login()) + mensaje de éxito
        $this->dispatch('abrir-modal-login', mensaje: '¡Cuenta creada! Inicia sesión para continuar.');
    }

    /** Centraliza el reset del formulario (DRY) */
    private function resetForm(): void
    {
        $this->name                  = '';
        $this->email                 = '';
        $this->password              = '';
        $this->password_confirmation = '';
        $this->mensajeError          = '';
        $this->resetValidation();
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.auth.register-modal');
    }
}
