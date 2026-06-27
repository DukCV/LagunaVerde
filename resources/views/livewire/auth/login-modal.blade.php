{{--
    ══════════════════════════════════════════════════════════════════════════
    VISTA: Modal de inicio de sesión
    Componente: App\Livewire\Auth\LoginModal

    Estructura:
      - Div raíz vacío (requerido por Livewire 4 — siempre debe existir)
      - Overlay oscuro (backdrop) — clic fuera cierra el modal
      - Card central con animación scale-in al aparecer
      - Formulario con email, contraseña y recordarme
      - Mensajes de error inline (Livewire validation + error auth)
      - Indicador de carga durante el intento de login

    IMPORTANTE (Livewire 4):
      Livewire exige que la vista SIEMPRE tenga un elemento raíz HTML,
      incluso cuando el modal está oculto. Por eso el @if está DENTRO del <div>
      y no directamente en el nivel raíz de la plantilla.

    Seguridad:
      - XSS: Blade escapa automáticamente {{ }} — nunca usar {!! !!} aquí
      - CSRF: protegido automáticamente por Livewire en cada petición
    ══════════════════════════════════════════════════════════════════════════
--}}

{{-- Elemento raíz permanente — Livewire 4 lo requiere siempre --}}
<div>
    @if ($mostrarModal)
        {{-- ── Overlay / Backdrop ──────────────────────────────────────────── --}}
        {{-- wire:click.self: solo cierra si se hace clic DIRECTAMENTE en el overlay --}}
        {{-- (no en los hijos), evitando cierres accidentales --}}
        <div
            id="login-modal-overlay"
            wire:click.self="cerrarModal"
            class="auth-modal-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="login-modal-title"
        >
            {{-- ── Card del modal ──────────────────────────────────────────── --}}
            <div class="auth-modal-card modal-scale-in">

                {{-- ── Encabezado del modal ────────────────────────────────── --}}
                <div class="auth-modal-header">
                    {{-- Logo sin fondo azul --}}
                    <img
                        src="{{ asset('img/LOGO_1.png') }}"
                        alt="Logo Laguna Verde"
                        class="auth-modal-logo"
                        loading="lazy"
                    >

                    <div>
                        <h2 id="login-modal-title" class="auth-modal-title">
                            Iniciar Sesión
                        </h2>
                        <p class="auth-modal-subtitle">
                            Accede a tu cuenta del Consejo Ciudadano
                        </p>
                    </div>

                    {{-- Botón de cierre en la esquina superior derecha --}}
                    <button
                        wire:click="cerrarModal"
                        class="auth-modal-close-btn"
                        aria-label="Cerrar modal de inicio de sesión"
                        type="button"
                    >
                        {{-- Ícono × SVG (accesible y sin fuente de íconos externa) --}}
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                             aria-hidden="true">
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- ── Cuerpo del formulario ──────────────────────────────── --}}
                <div class="auth-modal-body">

                    {{-- ── Aviso de éxito al venir del registro ──────────────── --}}
                    @if ($mensajeExito)
                        <div class="auth-success-banner" role="status" aria-live="polite">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" class="auth-success-icon">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg>
                            {{-- Blade escapa el mensaje → protección XSS --}}
                            <span>{{ $mensajeExito }}</span>
                        </div>
                    @endif

                    {{-- ── Mensaje de error de autenticación (no de validación) ── --}}
                    @if ($mensajeError)
                        <div class="auth-error-banner" role="alert" aria-live="assertive">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 aria-hidden="true" class="auth-error-icon">
                                <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>
                            </svg>
                            {{-- Blade escapa el mensaje → protección XSS --}}
                            <span>{{ $mensajeError }}</span>
                        </div>
                    @endif

                    <form wire:submit="login" id="login-modal-form" class="auth-form" novalidate>

                        {{-- ── Campo: Correo electrónico ────────────────────── --}}
                        <div class="auth-field-group">
                            <label for="login-email" class="auth-label">
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
                                    id="login-email"
                                    type="email"
                                    wire:model="email"
                                    class="auth-input @error('email') auth-input--error @enderror"
                                    placeholder="tu@correo.mx"
                                    autocomplete="email"
                                    required
                                    aria-describedby="@error('email') login-email-error @enderror"
                                >
                            </div>
                            {{-- Error de validación Livewire --}}
                            @error('email')
                                <p id="login-email-error" class="auth-field-error" role="alert">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- ── Campo: Contraseña ─────────────────────────────── --}}
                        <div class="auth-field-group">
                            <label for="login-password" class="auth-label">
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
                                    id="login-password"
                                    type="password"
                                    wire:model="password"
                                    class="auth-input @error('password') auth-input--error @enderror"
                                    placeholder="••••••••"
                                    autocomplete="current-password"
                                    required
                                    aria-describedby="@error('password') login-password-error @enderror"
                                >
                            </div>
                            @error('password')
                                <p id="login-password-error" class="auth-field-error" role="alert">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- ── Fila: Recordarme --}}
                        <div class="auth-remember-row">
                            <label class="auth-remember-label">
                                <input
                                    id="login-remember"
                                    type="checkbox"
                                    wire:model="recordarme"
                                    class="auth-checkbox"
                                >
                                <span>Recordarme</span>
                            </label>
                        </div>

                        {{-- ── Botón de envío ────────────────────────────────── --}}
                        <button
                            type="submit"
                            id="login-submit-btn"
                            class="auth-submit-btn"
                            wire:loading.attr="disabled"
                            wire:loading.class="auth-submit-btn--loading"
                        >
                            {{-- Estado normal --}}
                            <span wire:loading.remove wire:target="login">
                                🔐 Iniciar Sesión
                            </span>
                            {{-- Estado de carga: spinner SVG animado --}}
                            <span wire:loading wire:target="login" class="auth-spinner-wrap">
                                <svg class="auth-spinner" xmlns="http://www.w3.org/2000/svg" fill="none"
                                     viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Verificando...
                            </span>
                        </button>

                    </form>
                </div>{{-- /.auth-modal-body --}}
            </div>{{-- /.auth-modal-card --}}
        </div>{{-- /.auth-modal-overlay --}}
    @endif
</div>{{-- Elemento raíz requerido por Livewire 4 --}}
