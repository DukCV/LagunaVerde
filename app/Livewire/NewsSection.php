<?php

namespace App\Livewire;

use App\Concerns\ValidatesUuid;
use App\Services\NewsService;
use Livewire\Component;

/**
 * Componente Livewire: sección de noticias del home.
 *
 * RESPONSABILIDADES (principio de responsabilidad única):
 *  - Gestionar la navegación hacia el detalle de una noticia por UUID.
 *  - Obtener las últimas noticias publicadas vía NewsService (nunca directamente de Eloquent).
 *
 * El enlace al listado completo ("Ver todas las noticias") es un <a> con
 * wire:navigate directo a route('news') — no requiere acción del servidor.
 *
 * REFACTORIZACIÓN DRY:
 *  La query que antes vivía en fetchLatestNews() fue eliminada de este componente.
 *  Ahora se delega completamente a NewsService::getLatestForHome(), que a su vez
 *  delega a NewsRepository::latestForHome(). Esto garantiza que:
 *   1. Hay una única fuente de verdad para la query del home.
 *   2. Cambios en columnas, relaciones o límite solo se aplican en el repositorio.
 *   3. Este componente no conoce ni el modelo News ni la BD.
 *
 * SEGURIDAD:
 *  - UUID validado con regex antes de cualquier consulta a la BD.
 *  - News::published() en el repositorio garantiza que solo noticias publicadas
 *    son accesibles, reforzado además por el Global Scope PublishedScope.
 *  - abort(404) silencioso: sin distinción entre "no existe" y "no publicada"
 *    → previene enumeración de recursos.
 *  - Toda salida en la vista usa {{ }} → escape XSS automático de Blade.
 */
class NewsSection extends Component
{
    use ValidatesUuid;

    // ── Máximo de noticias a mostrar en el home ──────────────────────────
    // Centralizado aquí para que el componente controle la presentación,
    // pero la lógica de la query vive en el repositorio.
    private const MAX_ITEMS = 3;

    // ════════════════════════════════════════════════════════════════════
    //  ACCIONES PÚBLICAS
    // ════════════════════════════════════════════════════════════════════

    /**
     * Redirige al detalle usando UUID — nunca el ID entero.
     *
     * SEGURIDAD:
     *  - Valida el UUID con regex antes de tocar la BD.
     *  - Confirma existencia + estado publicado antes de redirigir.
     *  - Eloquent usa prepared statements → sin SQL Injection.
     *  - abort(404) ante UUID inválido o no publicado → sin fuga de información.
     *  - No se expone si la noticia existe pero no está publicada.
     *  - El Global Scope PublishedScope actúa como red de seguridad adicional.
     */
    public function openNews(string $uuid): void
    {
        // Rechazar cualquier entrada que no sea un UUID RFC-4122 válido
        if (! $this->isValidUuid($uuid)) {
            abort(404);
        }

        // Verificar existencia + estado publicado sin exponer datos innecesarios.
        // News::published() aplica el scope local; el Global Scope es la red de seguridad.
        $exists = \App\Models\News::published()
                      ->where('uuid', $uuid)
                      ->exists();

        abort_unless($exists, 404);

        $this->redirect(route('news.show', $uuid), navigate: true);
    }

    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    /**
     * Inyecta NewsService en el método render (inyección por método de Livewire).
     *
     * VENTAJA: Livewire gestiona el ciclo de vida del servicio;
     * no es necesario declararlo en el constructor ni como propiedad.
     */
    public function render(NewsService $service)
    {
        return view('livewire.news-section', [
            // getLatestForHome() delega a NewsRepository::latestForHome()
            // → única fuente de verdad, sin queries duplicadas en este componente.
            'articles' => $service->getLatestForHome(self::MAX_ITEMS),
        ]);
    }
}
