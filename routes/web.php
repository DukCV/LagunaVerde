<?php

use Illuminate\Support\Facades\Route;

// ════════════════════════════════════════════════════════════════════════
//  RUTAS PÚBLICAS
// ════════════════════════════════════════════════════════════════════════

/**
 * Rutas informativas principales con rate limiting para mitigar DoS básico.
 *
 * SEGURIDAD (throttle:200,1):
 *  - 200 peticiones por minuto por IP — transparente para usuarios legítimos.
 *  - Protege contra scraping masivo y ataques de denegación de servicio.
 *  - Devuelve HTTP 429 con cabecera Retry-After al superar el límite.
 *  - El límite es más permisivo que el de noticias (120) porque estas páginas
 *    no generan carga de BD y su contenido es mayormente estático.
 */
Route::middleware('throttle:200,1')->group(function () {
    Route::get('/', fn () => view('home'))->name('home');
    Route::get('/quienes-somos', fn () => view('about'))->name('about');
    Route::get('/contacto', fn () => view('contact'))->name('contact');
});

// ── Noticias ─────────────────────────────────────────────────────────────────────────

/**
 * Grupo de rutas públicas de noticias con rate limiting.
 *
 * SEGURIDAD (throttle:120,1):
 *  - 120 peticiones por minuto por IP → suficiente para cualquier usuario legítimo.
 *  - Protege contra:
 *    · DoS básico: satura el servidor con peticiones masivas.
 *    · Scraping: descarga masiva del contenido editorial.
 *    · Enumeración de UUIDs por fuerza bruta en /noticias/{uuid}.
 *  - Devuelve HTTP 429 automáticamente con cabecera Retry-After.
 *  - Complementa el rate limiting interno en los componentes Livewire
 *    (updatingSearch en NewsIndex) → defensa en capas.
 *  - El middleware 'throttle' de Laravel usa caché (Redis/file) → sin
 *    sobrecarga de BD por cada petición.
 */
Route::middleware('throttle:120,1')->group(function () {

    // Listado paginado de noticias publicadas.
    Route::get('/noticias', fn () => view('news'))->name('news');

    /**
     * Detalle de noticia por UUID.
     * El constraint regex rechaza cualquier string no UUID antes del closure.
     * Doble validación: router (regex) + servicio (preg_match) → defensa en profundidad.
     */
    Route::get('/noticias/{uuid}', fn (string $uuid) => view('NewDetail', ['uuid' => $uuid]))
        ->name('news.show')
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

}); // fin del grupo throttle noticias

// ── Eventos ──────────────────────────────────────────────────────────────

/**
 * Grupo de rutas de eventos con rate limiting.
 *
 * SEGURIDAD (throttle:120,1):
 *  - Mismo límite que noticias: ambas sirven contenido dinámico con BD.
 *  - Protege contra enumeración de UUIDs en /eventos/{uuid} y /inscripcion.
 *  - El constraint regex como primera línea de defensa antes del closure.
 *  - Devuelve HTTP 429 con cabecera Retry-After al superar el límite.
 */
Route::middleware('throttle:120,1')->group(function () {

    Route::get('/eventos', fn () => view('events'))->name('events');

    /**
     * Detalle de evento por UUID.
     * El constraint regex rechaza cualquier string no UUID antes del closure.
     * EventDetailService valida el UUID una segunda vez — defensa en profundidad.
     * El ID entero NUNCA aparece en la URL ni en el estado Livewire.
     */
    Route::get('/eventos/{uuid}', fn (string $uuid) => view('MainEventDetailPage', ['uuid' => $uuid]))
        ->name('events.show')
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

    /**
     * Inscripción a evento — placeholder hasta activar el módulo.
     * TODO: redirigir directamente a la página de detalle con el formulario activo.
     */
    Route::get('/eventos/{uuid}/inscripcion', fn (string $uuid) => redirect()->route('events.show', $uuid))
        ->name('events.register')
        ->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

}); // fin del grupo throttle eventos

// ── Colaboradores ────────────────────────────────────────────────────────

/**
 * Listado público de socios colaboradores con rate limiting.
 *
 * SEGURIDAD (throttle:120,1):
 *  - Mismo límite que noticias/eventos: sirve contenido dinámico con BD
 *    (búsqueda, filtro de categoría, orden y paginación).
 *  - Protege contra scraping masivo y abuso del formulario de búsqueda.
 *  - Devuelve HTTP 429 con cabecera Retry-After al superar el límite.
 *  - Complementa el rate limiting interno de CollaboratorsIndex
 *    (updatingSearch) → defensa en capas, mismo patrón que noticias/eventos.
 */
Route::get('/colaboradores', fn () => view('collaborators'))
    ->middleware('throttle:120,1')
    ->name('collaborators');

// ── Galería Multimedia ────────────────────────────────────────────────

/**
 * Galería multimedia pública con rate limiting.
 *
 * SEGURIDAD (throttle:150,1):
 *  - Límite ligeramente más permisivo que noticias/eventos ya que no tiene
 *    parámetros UUID enumerables; el estado se gestiona íntegramente en
 *    el componente Livewire vía wire:model + #[Url].
 *  - Protege contra scraping masivo de activos multimedia.
 */
Route::get('/multimedia', fn () => view('Multimedia.MainMediaGalleryPage'))
    ->middleware('throttle:150,1')
    ->name('multimedia');

// ── Archivos del disco 'public' (portadas, galerías, etc.) ───────────────
/**
 * Sirve archivos sin depender de 'php artisan storage:link'.
 *
 * SEGURIDAD (throttle:200,1):
 *  - Endpoint de solo lectura, pero limitado igual: mitiga scraping masivo
 *    y DoS básico sobre archivos potencialmente pesados (video).
 *
 * Ver App\Http\Controllers\MediaController para el porqué (fiabilidad del
 * symlink en hosting compartido) y el detalle de seguridad/caché.
 */
Route::get('/media/{path}', [\App\Http\Controllers\MediaController::class, 'show'])
    ->where('path', '.*')
    ->middleware('throttle:200,1')
    ->name('media.show');

// ════════════════════════════════════════════════════════════════════════
//  RUTAS AUTENTICADAS
// ════════════════════════════════════════════════════════════════════════

Route::view('/dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// ── Panel de Administración ───────────────────────────────────────────────
// Solo accesible para usuarios autenticados, verificados y con rol Administrador.
// El middleware 'admin' (EnsureAdministrator) aborta con 403 si el rol falla.
// throttle:60,1 limita a 60 peticiones/minuto por IP — mitiga DoS básico.
Route::get('/admin', \App\Livewire\Admin\Dashboard::class)
    ->middleware(['auth', 'verified', 'admin', 'throttle:60,1'])
    ->name('admin.dashboard');

require __DIR__ . '/settings.php';
