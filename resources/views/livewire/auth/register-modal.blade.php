{{--
    ══════════════════════════════════════════════════════════════════════════
    VISTA: Modal de registro de usuario
    Componente: App\Livewire\Auth\RegisterModal

    Reutiliza las clases .auth-* definidas para login-modal.blade.php
    (resources/css/app.css) — misma identidad visual, sin CSS nuevo.

    Seguridad: Blade escapa {{ }} automáticamente → protección XSS.
    ══════════════════════════════════════════════════════════════════════════
--}}

{{-- Elemento raíz permanente — Livewire 4 lo requiere siempre --}}
<div>
    @if ($mostrarModal)
        {{-- wire:click.self: solo cierra al hacer clic en el overlay, no en hijos --}}
        <div
            id="register-modal-overlay"
            wire:click.self="cerrarModal"
            class="auth-modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="register-modal-title"
        >
            <div class="auth-modal-card modal-scale-in">

                {{-- ── Encabezado ──────────────────────────────────────────── --}}
                <div class="auth-modal-header">
                    <img
                        src="{{ asset('img/LOGO_1.png') }}"
                        alt="Logo Laguna Verde"
                        class="auth-modal-logo"
                        loading="lazy"
                    >

                    <div>
                        <h2 id="register-modal-title" class="auth-modal-title">
                            Crear Cuenta
                        </h2>
                        <p class="auth-modal-subtitle">
                            Únete al Consejo Ciudadano
                        </p>
                    </div>

                    <button
                        wire:click="cerrarModal"
                        class="auth-modal-close-btn"
                        aria-label="Cerrar modal de registro"
                        type="button"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Cuerpo del formulario ──────────────────────────────── --}}
                <div class="auth-modal-body">

                    {{-- Error general (ej. bloqueo por rate limit) --}}
                    @if ($mensajeError)
                        <div class="auth-error-banner" role="alert" aria-live="assertive">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" class="auth-error-icon">
                                <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>
                            </svg>
                            <span>{{ $mensajeError }}</span>
                        </div>
                    @endif

                    <form wire:submit="registrar" id="register-modal-form" class="auth-form" novalidate>

                        {{-- ── Campo: Nombre completo ────────────────────────── --}}
                        <div class="auth-field-group">
                            <label for="register-name" class="auth-label">
                                Nombre completo
                            </label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/>
                                    </svg>
                                </span>
                                <input
                                    id="register-name"
                                    type="text"
                                    wire:model="name"
                                    class="auth-input @error('name') auth-input--error @enderror"
                                    placeholder="Tu nombre completo"
                                    autocomplete="name"
                                    maxlength="150"
                                    required
                                    aria-describedby="@error('name') register-name-error @enderror"
                                >
                            </div>
                            @error('name')
                                <p id="register-name-error" class="auth-field-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- ── Campo: Correo electrónico ────────────────────── --}}
                        <div class="auth-field-group">
                            <label for="register-email" class="auth-label">
                                Correo electrónico
                            </label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <rect width="20" height="16" x="2" y="4" rx="2"/>
                                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                    </svg>
                                </span>
                                <input
                                    id="register-email"
                                    type="email"
                                    wire:model="email"
                                    class="auth-input @error('email') auth-input--error @enderror"
                                    placeholder="tu@correo.mx"
                                    autocomplete="email"
                                    maxlength="190"
                                    required
                                    aria-describedby="@error('email') register-email-error @enderror"
                                >
                            </div>
                            @error('email')
                                <p id="register-email-error" class="auth-field-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- ── Campo: Contraseña ─────────────────────────────── --}}
                        <div class="auth-field-group">
                            <label for="register-password" class="auth-label">
                                Contraseña
                            </label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input
                                    id="register-password"
                                    type="password"
                                    wire:model="password"
                                    class="auth-input @error('password') auth-input--error @enderror"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                    required
                                    aria-describedby="@error('password') register-password-error @enderror"
                                >
                            </div>
                            @error('password')
                                <p id="register-password-error" class="auth-field-error" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- ── Campo: Confirmar contraseña ──────────────────── --}}
                        <div class="auth-field-group">
                            <label for="register-password-confirmation" class="auth-label">
                                Confirmar contraseña
                            </label>
                            <div class="auth-input-wrap">
                                <span class="auth-input-icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                         stroke-linejoin="round">
                                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <input
                                    id="register-password-confirmation"
                                    type="password"
                                    wire:model="password_confirmation"
                                    class="auth-input"
                                    placeholder="••••••••"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        {{-- ── Botón de envío ────────────────────────────────── --}}
                        <button
                            type="submit"
                            id="register-submit-btn"
                            class="auth-submit-btn"
                            wire:loading.attr="disabled"
                            wire:loading.class="auth-submit-btn--loading"
                        >
                            <span wire:loading.remove wire:target="registrar">
                                📝 Registrar
                            </span>
                            <span wire:loading wire:target="registrar" class="auth-spinner-wrap">
                                <svg class="auth-spinner" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Creando cuenta...
                            </span>
                        </button>

                    </form>
                </div>{{-- /.auth-modal-body --}}
            </div>{{-- /.auth-modal-card --}}
        </div>{{-- /.auth-modal-overlay --}}
    @endif
</div>{{-- Elemento raíz requerido por Livewire 4 --}}
