<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SERVICIO: Registro de nuevos usuarios desde el modal público
//
//  Responsabilidad única (SRP): crea la cuenta y asigna el rol base, separado
//  del componente Livewire — mismo patrón que LoginService.
//
//  NO usa App\Actions\Fortify\CreateNewUser: ese action es exclusivo de la
//  ruta /register de Fortify (scaffolding sin uso en el sitio público, ver
//  LoginModal). Este servicio es el equivalente de registro para el modal inline.
//
//  Seguridad:
//    - Rate limiting por IP, sin limpiar tras éxito (a diferencia del login):
//      el riesgo aquí es alta tasa de ALTAS desde una misma IP (bots/spam),
//      no un usuario legítimo reintentando su contraseña.
//    - Email único + password fuerte ya validados en el componente antes de llamar.
//    - 'password' se hashea automáticamente vía el cast 'hashed' del modelo.
// ══════════════════════════════════════════════════════════════════════════════

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class RegisterService
{
    // Rol asignado a todo registro público — magic string local (mismo
    // criterio que UserSeeder/TeamSection, ver AdminUsersRepository::ROL_USUARIO_NORMAL)
    private const ROL_USUARIO_NORMAL = 'Usuario Normal';

    // Límite más estricto que el login: una alta en BD es más costosa que un intento fallido
    private const MAX_INTENTOS     = 5;
    private const SEGUNDOS_BLOQUEO = 600; // 10 minutos

    /**
     * Crea un usuario nuevo con el rol base, limitado por IP.
     *
     * @return array{success: bool, message: string}
     */
    public function registrar(string $name, string $email, string $password, string $ip): array
    {
        // Clave de throttle solo por IP: el objetivo es limitar ALTAS por origen
        $clave = 'registro_modal|' . $ip;

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundosRestantes = RateLimiter::availableIn($clave);

            Log::warning('Registro bloqueado por rate limiting', ['ip' => $ip]);

            return [
                'success' => false,
                'message' => "Demasiados intentos de registro. Espera {$segundosRestantes} segundo(s).",
            ];
        }

        // Cuenta este intento ya validado — NUNCA se limpia tras éxito (a propósito)
        RateLimiter::hit($clave, self::SEGUNDOS_BLOQUEO);

        $rolId = Role::where('name', self::ROL_USUARIO_NORMAL)->firstOrFail()->id;

        // Transacción: evita un usuario creado sin rol si el sync() fallara
        $usuario = DB::transaction(function () use ($name, $email, $password, $rolId) {
            $usuario = User::create([
                'name'     => $name,
                'email'    => $email,
                'password' => $password, // cast 'hashed' lo cifra al guardar
            ]);

            $usuario->roles()->syncWithoutDetaching([$rolId]);

            return $usuario;
        });

        Log::info('Registro exitoso', ['user_id' => $usuario->id, 'ip' => $ip]);

        return [
            'success' => true,
            'message' => 'Cuenta creada correctamente.',
        ];
    }
}
