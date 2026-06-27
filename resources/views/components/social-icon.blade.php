@props(['name'])

@php
    /*
     * Logotipos de redes sociales — usado en la tarjeta pública de
     * colaboradores, el modal de detalles, y el panel de administración
     * (lista y formulario de socios). Trazado vectorial inline — evita
     * dependencias de red, igual que <x-admin-icon> en el panel admin.
     *
     * A diferencia de <x-admin-icon> (iconos de trazo, stroke-based), estos
     * son logotipos de marca de relleno sólido (fill-based), salvo 'website'
     * (no es una red social, por lo que reutiliza el trazo genérico
     * globe-alt de <x-admin-icon> en vez de un logo de marca).
     *
     * fill-rule="evenodd" en el <path> permite que sub-trazados anidados
     * (p. ej. el aro de la lente en 'instagram', el triángulo en 'youtube')
     * se rendericen como un hueco en vez de rellenarse del mismo color.
     *
     * Uso: <x-social-icon name="facebook" class="w-5 h-5" />
     */
    $paths = [

        'facebook' => 'M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54v-2.89h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562v1.875h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.992 22 12z',

        'twitter' => 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644z',

        'linkedin' => 'M19 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM8.339 18.337H5.667v-7.4h2.672zM7 9.91a1.555 1.555 0 1 1 0-3.109 1.555 1.555 0 0 1 0 3.109zm11.339 8.427h-2.669v-3.965c0-.945-.017-2.157-1.317-2.157-1.318 0-1.52 1.03-1.52 2.093v4.029h-2.669v-7.4h2.561v1.014h.036a2.81 2.81 0 0 1 2.526-1.387c2.703 0 3.203 1.778 3.203 4.092z',

        'instagram' => 'M12 2c2.717 0 3.056.01 4.123.06 1.064.05 1.79.217 2.428.465a4.903 4.903 0 0 1 1.772 1.153 4.903 4.903 0 0 1 1.153 1.772c.247.637.415 1.363.465 2.427.05 1.066.06 1.405.06 4.122s-.01 3.056-.06 4.122c-.05 1.064-.218 1.79-.465 2.428a4.903 4.903 0 0 1-1.153 1.772 4.903 4.903 0 0 1-1.772 1.153c-.637.247-1.363.415-2.428.465-1.066.05-1.405.06-4.122.06s-3.056-.01-4.122-.06c-1.064-.05-1.79-.218-2.428-.465a4.903 4.903 0 0 1-1.772-1.153 4.903 4.903 0 0 1-1.153-1.772c-.247-.637-.415-1.363-.465-2.428C2.01 15.056 2 14.717 2 12s.01-3.056.06-4.122c.05-1.064.218-1.79.465-2.427A4.903 4.903 0 0 1 3.678 3.68 4.903 4.903 0 0 1 5.45 2.525c.637-.247 1.363-.415 2.428-.465C8.944 2.01 9.283 2 12 2zm0 4.595a5.403 5.403 0 1 0 0 10.806 5.403 5.403 0 0 0 0-10.806zm0 8.91a3.508 3.508 0 1 1 0-7.015 3.508 3.508 0 0 1 0 7.015zm5.884-9.114a1.262 1.262 0 1 1-2.524 0 1.262 1.262 0 0 1 2.524 0z',

        // Marco redondeado + triángulo de "play" recortado mediante fill-rule="evenodd"
        'youtube' => 'M5 5h14a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H5a3 3 0 0 1-3-3V8a3 3 0 0 1 3-3zM10 9l5 3-5 3z',

    ];

    // 'website' no es una red social: reutiliza el trazo de globe-alt (stroke).
    $esSitioWeb = $name === 'website';
    $path       = $paths[$name] ?? '';
@endphp

@if ($esSitioWeb)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        aria-hidden="true"
        {{ $attributes }}
    >
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
    </svg>
@else
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        aria-hidden="true"
        {{ $attributes }}
    >
        <path fill-rule="evenodd" clip-rule="evenodd" d="{{ $path }}" />
    </svg>
@endif
