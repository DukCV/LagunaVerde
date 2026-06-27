<?php

namespace App\Livewire\Admin\Events\Form;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Form;

/**
 * Estado de la sección "Colaboradores Invitados": el interruptor principal
 * y los filtros de la grilla de selección (búsqueda + tipo).
 *
 * La LISTA de colaboradores ya añadidos NO vive aquí — vive en
 * EventForm::$selectedCollaborators, junto con EventForm::$customLogoUploads,
 * por la misma razón que el slider multimedia vive en el componente
 * principal y no en un Form object: WithFileUploads no puede alojarse en
 * Livewire\Form (ver docblock de la clase EventForm).
 */
class CollaboratorsForm extends Form
{
    public bool $withCollaborators = false;
    public string $search = '';
    public string $type = 'todos';

    private const MAX_SEARCH_LENGTH = 100;
    private const MAX_TYPE_LENGTH   = 30;
    private const RATE_LIMIT_MAX    = 30;
    private const RATE_LIMIT_DECAY  = 60;

    /**
     * Límite de búsquedas por sesión — mismo patrón que
     * CollaboratorsIndex::updatingSearch() (listado público de socios):
     * previene el abuso de la búsqueda como vector de scraping/DoS, también
     * dentro del panel admin.
     */
    public function updatingSearch(string $value): void
    {
        $this->search = mb_substr($value, 0, self::MAX_SEARCH_LENGTH);

        $key = 'admin-event-collaborators-search:' . request()->fingerprint();

        if (RateLimiter::tooManyAttempts($key, self::RATE_LIMIT_MAX)) {
            $this->search = '';

            return;
        }

        RateLimiter::hit($key, self::RATE_LIMIT_DECAY);
    }

    public function updatingType(string $value): void
    {
        $this->type = mb_substr($value, 0, self::MAX_TYPE_LENGTH);
    }
}
