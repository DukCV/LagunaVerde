{{--
    components/buttons/view-all.blade.php
    ─────────────────────────────────────────────────────────────────────
    Botón "ver todo" estandarizado para los encabezados de sección del
    home (Colaboradores, Noticias, Eventos). Única fuente de verdad para
    este estilo — evita duplicar las mismas clases Tailwind en 3 vistas.

    Props:
      - href (string, obligatorio): URL de destino. Generar siempre con
        route() en la vista que invoca el componente, nunca como cadena
        hardcodeada, para evitar enlaces rotos o manipulables.

    Atributos adicionales (wire:navigate, class extra, aria-label, etc.)
    se combinan automáticamente con las clases base vía $attributes->merge().

    Uso:
      <x-buttons.view-all :href="route('news')" wire:navigate>
          Ver todas las noticias
      </x-buttons.view-all>
    ─────────────────────────────────────────────────────────────────────
--}}

@props(['href'])

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'inline-block px-6 py-3 border-2 border-blue-600 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all',
    ]) }}
>
    {{ $slot }}
</a>
