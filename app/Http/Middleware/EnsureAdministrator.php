<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización: bloquea acceso a rutas del panel admin
 * para cualquier usuario que no posea el rol 'Administrador'.
 *
 * SEGURIDAD:
 *  - Doble verificación: primero autenticación, luego rol específico.
 *  - Usa isAdministrator() del modelo User, que centraliza el magic string del rol.
 *  - En caso de fallo, aborta con 403 (no revela si la ruta existe o no).
 */
class EnsureAdministrator
{
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar autenticación antes de intentar acceder al usuario
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        // Verificar que el usuario autenticado tiene el rol de Administrador
        if (! auth()->user()->isAdministrator()) {
            abort(403, 'Acceso no autorizado: se requiere rol de Administrador.');
        }

        return $next($request);
    }
}
