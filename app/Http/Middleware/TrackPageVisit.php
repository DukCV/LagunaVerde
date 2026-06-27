<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de métricas: registra visitas del sitio público en la BD.
 *
 * Solo se registran peticiones GET de navegación normal:
 *  - Excluye peticiones AJAX y respuestas JSON (APIs internas de Livewire)
 *  - Excluye rutas que comiencen con 'admin' (métricas del panel no cuentan)
 *  - Excluye la ruta 'up' (health check de Laravel)
 *
 * El registro se hace DESPUÉS de generar la respuesta (post-middleware) para
 * no bloquear la entrega al usuario en caso de fallo de escritura.
 */
class TrackPageVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo rastrear navegación normal del sitio público
        $debeRegistrar = $request->isMethod('GET')
            && ! $request->ajax()
            && ! $request->wantsJson()
            && ! str_starts_with($request->path(), 'admin')
            && $request->path() !== 'up';

        if ($debeRegistrar) {
            // try/catch para no interrumpir la navegación si la BD falla
            try {
                PageVisit::create([
                    'session_id' => session()->getId(),
                    'ip_address' => $request->ip(),
                    'url'        => mb_substr($request->path(), 0, 500),
                    'visited_at' => now(),
                ]);
            } catch (\Throwable) {
                // Fallo silencioso: las métricas no deben afectar la experiencia del usuario
            }
        }

        return $response;
    }
}
