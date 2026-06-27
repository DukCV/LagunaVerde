@props(['links'])

{{--
    components/social-links.blade.php
    ─────────────────────────────────────────────────────────────────────
    Fila de botones circulares de redes sociales. ÚNICA fuente de verdad
    para este marcado — usado por la sección de colaboradores del home y
    de "Quiénes Somos" (mismo componente Livewire), y por la tarjeta del
    listado público /colaboradores. Mantiene ambas vistas visualmente
    idénticas sin duplicar el HTML (DRY).

    Props:
      - links (array<string, string|null>, obligatorio): mapa
        plataforma => URL. Puede incluir valores vacíos/null o URLs sin
        validar — este componente filtra ambos casos antes de renderizar,
        por lo que el llamador no necesita sanear el array de antemano.

    SEGURIDAD (defensa en profundidad):
    • Sólo se renderiza un enlace si la URL comienza con http:// o https://
      — descarta cualquier esquema peligroso ("javascript:", etc.) aunque
      el origen del dato no lo haya validado todavía.
    • e() en el atributo href → escape adicional ante caracteres especiales.
    • rel="noopener noreferrer" → evita que la pestaña abierta controle
      window.opener (tabnabbing).
    ─────────────────────────────────────────────────────────────────────
--}}

@php
    // Etiquetas legibles por plataforma — para title/aria-label.
    $etiquetas = [
        'website'   => 'Sitio web',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'twitter'   => 'Twitter / X',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
    ];

    // Sólo URLs http(s) no vacías — nunca se confía en el origen del dato.
    $enlacesSeguros = array_filter(
        $links,
        fn ($url) => is_string($url) && $url !== '' && preg_match('#^https?://#i', $url)
    );
@endphp

@if (! empty($enlacesSeguros))
    <div class="flex items-center gap-2 flex-wrap">
        @foreach ($enlacesSeguros as $plataforma => $url)
            @php $etiqueta = $etiquetas[$plataforma] ?? ucfirst($plataforma); @endphp
            <a
                href="{{ e($url) }}"
                target="_blank"
                rel="noopener noreferrer"
                title="{{ $etiqueta }}"
                aria-label="{{ $etiqueta }}"
                class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors shrink-0"
            >
                <x-social-icon name="{{ $plataforma }}" class="w-4 h-4" />
            </a>
        @endforeach
    </div>
@endif
