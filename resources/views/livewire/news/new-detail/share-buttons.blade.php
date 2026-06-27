{{--
    livewire/news/new-detail/share-buttons.blade.php
    ────────────────────────────────────────────────────────────────────
    Recibe: $shareLinks (array generado en PHP), $shareUrl (string)

    SEGURIDAD:
    • href de redes sociales generado en PHP con urlencode() → sin inyección.
    • e() en href para escapado adicional en atributos.
    • navigator.clipboard.writeText recibe $shareUrl escapado con e().
    • rel="noopener noreferrer" en todos los links externos.
    • target="_blank" bloqueado para evitar tab-napping en links internos.
    ────────────────────────────────────────────────────────────────────
--}}

<div x-data="{ copied: false }" class="flex flex-col gap-3">

    <span class="text-sm font-medium text-gray-600">Compartir este artículo</span>

    <div class="flex flex-wrap gap-2">

        @foreach ($shareLinks as $link)
            <a
                href="{{ e($link['href']) }}"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Compartir en {{ $link['name'] }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700
                       text-sm rounded-lg transition-colors {{ $link['color'] }}"
            >
                {{-- Facebook --}}
                @if ($link['icon'] === 'facebook')
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/>
                    </svg>
                {{-- Twitter / X --}}
                @elseif ($link['icon'] === 'twitter')
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/>
                    </svg>
                {{-- WhatsApp --}}
                @elseif ($link['icon'] === 'whatsapp')
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor"
                         viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.558 4.114 1.535 5.836L0 24l6.355-1.507A11.943 11.943 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.809 9.809 0 01-5.031-1.385l-.361-.214-3.741.981.998-3.648-.235-.374A9.818 9.818 0 012.182 12C2.182 6.58 6.58 2.182 12 2.182S21.818 6.58 21.818 12 17.42 21.818 12 21.818z"/>
                    </svg>
                @endif

                {{ $link['name'] }}
            </a>
        @endforeach

        {{-- Copiar enlace — client-side puro con Alpine.js, sin roundtrip --}}
        <button
            @click="
                navigator.clipboard.writeText('{{ e($shareUrl) }}')
                    .then(() => { copied = true; setTimeout(() => copied = false, 2000); })
                    .catch(() => {})
            "
            aria-label="Copiar enlace de la noticia"
            class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 text-gray-700
                   text-sm rounded-lg transition-colors hover:bg-gray-700 hover:text-white"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
            </svg>
            <span x-text="copied ? '¡Copiado!' : 'Copiar enlace'"></span>
        </button>

    </div>
</div>
