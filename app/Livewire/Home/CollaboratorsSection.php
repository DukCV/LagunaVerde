<?php

namespace App\Livewire\Home;

use App\DTOs\PartnerCardDto;
use App\Models\Partner;
use Livewire\Component;

/**
 * Componente Livewire: sección "Nuestros Colaboradores" del home y de
 * "Quiénes Somos" (esta última la reutiliza vía
 * <livewire:home.collaborators-section />).
 *
 * Tarjetas pequeñas (logo, nombre, categoría) en una grilla fija — ver
 * collaborators-section.blade.php y <x-collaborators.mini-card>. Ya no usa
 * <x-collaborators.card> (la tarjeta grande del listado público).
 */
class CollaboratorsSection extends Component
{
    /** @var array[] Cada elemento es un PartnerCardDto serializado vía toLivewire() */
    public array $collaborators = [];

    // Máximo fijo de tarjetas a mostrar en esta sección
    private const MAX_COLLABORATORS = 5;

    // ── Columnas mínimas necesarias para PartnerCardDto::fromModel() ────
    // Mismo listado que App\Repositories\PartnersRepository::LIST_COLUMNS
    // — evita traer columnas que la tarjeta nunca usa (p. ej. status interno).
    private const COLUMNS = [
        'id', 'name', 'type', 'who_they_are', 'how_they_support',
        'website', 'social_instagram', 'social_facebook', 'social_twitter', 'social_linkedin', 'social_youtube',
        'created_at', 'updated_at',
    ];

    /**
     * Carga los socios activos desde la base de datos (App\Models\Partner),
     * más recientes primero. with('media') evita N+1 al resolver el logo.
     */
    public function mount(): void
    {
        $this->collaborators = Partner::active()
            ->select(self::COLUMNS)
            ->with(['media:id,mediable_id,mediable_type,collection,path,disk,mime'])
            ->latest()
            ->take(self::MAX_COLLABORATORS)
            ->get()
            ->map(fn (Partner $partner) => PartnerCardDto::fromModel($partner)->toLivewire())
            ->all();
    }

    public function render()
    {
        return view('livewire.home.collaborators-section');
    }
}
