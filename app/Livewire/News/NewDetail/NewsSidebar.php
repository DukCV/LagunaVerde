<?php

namespace App\Livewire\News\NewDetail;

use App\DTOs\SidebarNewsDto;
use App\Services\NewsDetailService;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: barra lateral de la página de detalle.
 *
 * SECCIONES:
 *  1. Últimas 3 noticias publicadas (excluyendo la actual).
 *  2. Formulario de newsletter — sólo visual en esta fase.
 *  3. Banner de donaciones — enlace a la ruta correspondiente.
 *
 * SEGURIDAD:
 *  - #[Locked] en $latestNews y $currentUuid → sin tampering del cliente.
 *  - Las noticias se cargan con SidebarNewsDto: mínima exposición de datos.
 *  - wire:poll.10s actualiza la lista cada 10 s sin interacción del usuario.
 */
class NewsSidebar extends Component
{
    /** UUID de la noticia actual — usado para excluirla del sidebar. */
    #[Locked]
    public string $currentUuid = '';

    /**
     * Noticias del sidebar serializadas.
     * @var array[]  Representación array de SidebarNewsDto[]
     */
    #[Locked]
    public array $latestNews = [];

    public function mount(string $currentUuid, NewsDetailService $service): void
    {
        $this->currentUuid = $currentUuid;
        $this->refreshNews($service);
    }

    /**
     * Refresca la lista de noticias recientes.
     * Llamado por wire:poll para mantener el sidebar actualizado.
     */
    public function refreshNews(NewsDetailService $service): void
    {
        $this->latestNews = array_map(
            fn (SidebarNewsDto $dto) => $dto->toLivewire(),
            $service->getLatestForSidebar($this->currentUuid, limit: 3)
        );
    }

    public function render()
    {
        return view('livewire.news.new-detail.news-sidebar');
    }
}
