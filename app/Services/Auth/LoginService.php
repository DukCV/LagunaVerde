<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SERVICIO: Autenticación del modal de login
//
//  Responsabilidad única (SRP): encapsula la lógica de autenticación
//  del modal contextual, separándola del componente Livewire.
//
//  NO duplica la lógica de Fortify; Fortify maneja sus propias rutas /login.
//  Este servicio es exclusivo para el modal de autenticación inline.
//
//  Seguridad implementada:
//    - Rate limiting por clave compuesta (email + IP) — defensa en profundidad
//    - Registro de intentos fallidos en el log de errores (auditoría básica)
//    - No expone información sobre si el email existe o no (mensaje genérico)
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Services\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginService
{
    // ── Configuración del rate limiter ───────────────────────────────────────

    /** Número máximo de intentos permitidos antes de bloquear */
    private const MAX_INTENTOS = 5;

    /** Segundos de bloqueo tras superar el límite */
    private const SEGUNDOS_BLOQUEO = 60;

    /**
     * Intenta autenticar al usuario con las credenciales proporcionadas.
     *
     * @param  string  $email     Dirección de correo del usuario
     * @param  string  $password  Contraseña en texto plano (se compara con el hash)
     * @param  bool    $remember  Si se debe mantener la sesión activa (cookie persistente)
     * @param  string  $ip        Dirección IP del cliente para el rate limiting
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   bloqueado: bool,
     *   segundos_restantes: int
     * }
     */
    public function intentarLogin(
        string $email,
        string $password,
        bool   $remember,
        string $ip
    ): array {
        // Genera la clave de throttle: email (normalizado) + IP
        $claveThrottle = $this->generarClaveThrottle($email, $ip);

        // ── Verificar si el usuario está bloqueado por rate limiting ─────────
        if (RateLimiter::tooManyAttempts($claveThrottle, self::MAX_INTENTOS)) {
            $segundosRestantes = RateLimiter::availableIn($claveThrottle);

            // Registra el intento bloqueado para auditoría
            Log::warning('Intento de login bloqueado por rate limiting', [
                'email' => $email,
                'ip'    => $ip,
                'segundos_restantes' => $segundosRestantes,
            ]);

            return [
                'success'           => false,
                'message'           => "Demasiados intentos. Por favor espera {$segundosRestantes} segundo(s).",
                'bloqueado'         => true,
                'segundos_restantes'=> $segundosRestantes,
            ];
        }

        // ── Intentar autenticación ───────────────────────────────────────────
        $credenciales = [
            'email'    => $email,
            'password' => $password,
        ];

        if (Auth::attempt($credenciales, $remember)) {
            // Autenticación exitosa: limpiar el contador de intentos
            RateLimiter::clear($claveThrottle);

            // Registra el login exitoso (auditoría)
            Log::info('Login exitoso', [
                'user_id' => Auth::id(),
                'email'   => $email,
                'ip'      => $ip,
            ]);

            return [
                'success'           => true,
                'message'           => 'Autenticación exitosa.',
                'bloqueado'         => false,
                'segundos_restantes'=> 0,
            ];
        }

        // ── Credenciales incorrectas: incrementar contador ───────────────────
        RateLimiter::hit($claveThrottle, self::SEGUNDOS_BLOQUEO);

        // Registra el intento fallido (sin exponer si el email existe)
        Log::notice('Intento de login fallido', [
            'email' => $email,
            'ip'    => $ip,
            'intentos_restantes' => RateLimiter::remaining($claveThrottle, self::MAX_INTENTOS),
        ]);

        return [
            'success'           => false,
            // Mensaje genérico: no revela si el email existe en el sistema
            'message'           => 'Las credenciales proporcionadas no son correctas.',
            'bloqueado'         => false,
            'segundos_restantes'=> 0,
        ];
    }

    /**
     * Genera una clave única de throttle por usuario e IP.
     * Usa Str::lower + transliterate para normalizar caracteres especiales.
     */
    private function generarClaveThrottle(string $email, string $ip): string
    {
        return 'login_modal|' . Str::transliterate(Str::lower($email)) . '|' . $ip;
    }
}
