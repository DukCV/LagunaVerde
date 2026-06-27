<?php

namespace App\Providers;

// ── Repositorios — Noticias ─────────────────────────────────────────────
use App\Repositories\CommentRepository;
use App\Repositories\Events\EventIndexRepository;
use App\Repositories\NewsDetailRepository;
use App\Repositories\NewsRepository;

// ── Repositorios — Eventos ─────────────────────────────────────────────
use App\Repositories\Events\EventDetail\EventDetailRepository;
use App\Repositories\Home\Events\EventRepository;

// ── Servicios — Noticias ────────────────────────────────────────────────
use App\Services\CommentService;
use App\Services\Events\EventIndexService;
use App\Services\NewsDetailService;
use App\Services\NewsService;

// ── Servicios — Eventos ─────────────────────────────────────────────
use App\Services\Events\EventDetail\EventDetailService;
use App\Services\Home\Events\EventService;

// ── Repositorios — Multimedia ───────────────────────────────────────
use App\Repositories\Multimedia\MediaGalleryRepository;

// ── Servicios — Multimedia ──────────────────────────────────────────
use App\Services\Multimedia\MediaGalleryService;

// ── Servicios — Autenticación ───────────────────────────────
use App\Services\Auth\LoginService;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ── Módulo de noticias ──────────────────────────────────────────
        $this->app->singleton(NewsRepository::class);
        $this->app->singleton(NewsDetailRepository::class);
        $this->app->singleton(CommentRepository::class);
        $this->app->singleton(NewsService::class);
        $this->app->singleton(NewsDetailService::class);
        $this->app->singleton(CommentService::class);

        // ── Módulo de eventos ───────────────────────────────────────────
        $this->app->singleton(EventRepository::class);
        $this->app->singleton(EventService::class);
        $this->app->singleton(EventIndexRepository::class);
        $this->app->singleton(EventIndexService::class);
        $this->app->singleton(EventDetailService::class);   
        $this->app->singleton(EventDetailRepository::class);

        // ── Módulo de multimedia ────────────────────────────────────────
        $this->app->singleton(MediaGalleryRepository::class);
        $this->app->singleton(MediaGalleryService::class);

        // ── Módulo de autenticación ────────────────────────────────
        // Singleton: LoginService es liviano y no tiene estado entre peticiones
        $this->app->singleton(LoginService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS en producción (Hostinger provee SSL gratuito)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->configureDefaults();

        Relation::enforceMorphMap([
            'event' => \App\Models\Event::class,
            'news' => \App\Models\News::class,
            'project' => \App\Models\Project::class,
            'partner' => \App\Models\Partner::class,
        ]);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
