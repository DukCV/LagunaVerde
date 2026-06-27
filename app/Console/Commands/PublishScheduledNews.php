<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Models\Scopes\PublishedScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando: publica automáticamente las noticias programadas cuya fecha llegó.
 *
 * CONTEXTO:
 *  NewsForm guarda con status='scheduled' las noticias cuya fecha de
 *  publicación es futura. Este comando se ejecuta periódicamente (ver
 *  routes/console.php) y transiciona esas noticias a 'published' en cuanto
 *  published_at <= now().
 *
 * RENDIMIENTO:
 *  Una única sentencia UPDATE masiva sobre la columna indexada 'status'
 *  (whereIn('status', ...) del scope + published_at indexado) — O(log n) por
 *  fila afectada, sin cargar modelos en memoria. Escala correctamente con
 *  datasets masivos.
 *
 * INMUTABILIDAD:
 *  first_published_at se rellena solo si aún es NULL (COALESCE), respetando
 *  la invariante de "marca de primera publicación" usada por NewsForm para
 *  bloquear la fecha tras la primera publicación.
 *
 * SEGURIDAD:
 *  withoutGlobalScope(PublishedScope::class) es imprescindible: el scope
 *  público filtra status='published', por lo que sin el opt-out la consulta
 *  jamás encontraría filas en status='scheduled'.
 */
class PublishScheduledNews extends Command
{
    protected $signature = 'news:publish-scheduled';

    protected $description = 'Publica automáticamente las noticias programadas cuya fecha de publicación ya llegó';

    public function handle(): int
    {
        $publicadas = News::withoutGlobalScope(PublishedScope::class)
            ->where('status', 'scheduled')
            ->where('published_at', '<=', now())
            ->update([
                'status'             => 'published',
                'first_published_at' => DB::raw('COALESCE(`first_published_at`, `published_at`)'),
                'updated_at'         => now(),
            ]);

        if ($publicadas > 0) {
            $this->info("Se publicaron {$publicadas} noticia(s) programada(s).");
        }

        return self::SUCCESS;
    }
}
