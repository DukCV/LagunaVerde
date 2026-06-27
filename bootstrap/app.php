<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias de middleware para uso en rutas con nombre corto
        $middleware->alias([
            'admin'       => \App\Http\Middleware\EnsureAdministrator::class,
            'track.visit' => \App\Http\Middleware\TrackPageVisit::class,
        ]);

        // Rastrear visitas en todas las rutas web públicas
        $middleware->web(append: [
            \App\Http\Middleware\TrackPageVisit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
