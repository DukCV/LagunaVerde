<?php

namespace App\Livewire\News\NewDetail;

use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Componente Livewire: botones para compartir la noticia.
 *
 * Los links de redes sociales se construyen en PHP con URLs
 * codificadas — sin operaciones en el cliente salvo "Copiar enlace"
 * (manejado por Alpine.js, client-side puro, sin roundtrip).
 *
 * SEGURIDAD:
 *  - #[Locked] en $title y $shareUrl → sin tampering del cliente.
 *  - urlencode() en título y URL para los query strings de redes sociales.
 *  - e() en la vista para los atributos href → previene XSS.
 *  - La URL se genera con url()->current() en el servidor — nunca
 *    desde document.location del cliente.
 */
class ShareButtons extends Component
{
    #[Locked]
    public string $title = '';

    #[Locked]
    public string $shareUrl = '';

    public function mount(string $title = '', string $shareUrl = ''): void
    {
        $this->title    = mb_substr(strip_tags(trim($title)), 0, 220);
        $this->shareUrl = filter_var($shareUrl ?: url()->current(), FILTER_VALIDATE_URL)
            ? ($shareUrl ?: url()->current())
            : url()->current();
    }

    public function render()
    {
        return view('livewire.news.new-detail.share-buttons', [
            'shareLinks' => $this->buildShareLinks(),
        ]);
    }

    /**
     * Construye los links de compartir con URL y título codificados.
     * Los valores se codifican con urlencode() → sin inyección en query strings.
     */
    private function buildShareLinks(): array
    {
        $url   = urlencode($this->shareUrl);
        $title = urlencode($this->title);

        return [
            [
                'name'  => 'Facebook',
                'icon'  => 'facebook',
                'href'  => "https://www.facebook.com/sharer/sharer.php?u={$url}",
                'color' => 'hover:bg-blue-600 hover:text-white',
            ],
            [
                'name'  => 'Twitter / X',
                'icon'  => 'twitter',
                'href'  => "https://twitter.com/intent/tweet?text={$title}&url={$url}",
                'color' => 'hover:bg-sky-500 hover:text-white',
            ],
            [
                'name'  => 'WhatsApp',
                'icon'  => 'whatsapp',
                'href'  => "https://wa.me/?text={$title}%20{$url}",
                'color' => 'hover:bg-green-600 hover:text-white',
            ],
        ];
    }
}
