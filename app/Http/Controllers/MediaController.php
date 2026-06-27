<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sirve archivos del disco 'public' directamente desde la aplicación.
 *
 * POR QUÉ EXISTE (contexto Hostinger):
 *  'php artisan storage:link' crea un enlace simbólico público/storage →
 *  storage/app/public. En hosting compartido este enlace no es fiable:
 *  symlink() suele estar deshabilitada por el proveedor, o la raíz pública
 *  real del dominio no coincide exactamente con public/ — en ambos casos
 *  el enlace nunca llega a crearse o no resuelve donde el dominio espera,
 *  y cualquier URL bajo /storage/... devuelve 404 aunque el archivo exista
 *  en disco. Este endpoint sirve el archivo desde PHP, así que la galería
 *  multimedia (portadas, slider de eventos, noticias) funciona sin
 *  depender de ese enlace ni de cómo Hostinger mapee el doc root.
 *
 * SEGURIDAD:
 *  - El disco está fijo a 'public' — jamás se expone el disco 'local'
 *    (storage/app/private), donde podrían vivir archivos sensibles.
 *  - Se rechaza cualquier ruta con '..' antes de tocar el filesystem,
 *    además de la normalización propia de Flysystem (defensa en profundidad).
 *  - response()->file() delega en BinaryFileResponse: soporta peticiones
 *    Range (necesario para adelantar/retroceder video) y cabeceras
 *    condicionales (ETag/Last-Modified) para 304 Not Modified.
 *
 * RENDIMIENTO:
 *  - Cache-Control de un año + 'immutable': los nombres de archivo son
 *    generados aleatoriamente al subir (nunca se reescribe un mismo path
 *    con contenido distinto), así que cachear agresivamente es seguro.
 *  - Sin consultas a BD: el archivo se sirve solo por ruta de disco, sin
 *    acoplarse al modelo Media — cero overhead de Eloquent por petición.
 */
class MediaController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        if (str_contains($path, '..')) {
            abort(404);
        }

        $disco = Storage::disk('public');

        if (! $disco->exists($path)) {
            abort(404);
        }

        return response()->file($disco->path($path), [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
