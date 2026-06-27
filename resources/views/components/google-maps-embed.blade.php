@props([
    'location' => null,
    'zoom' => 15,
    'placeholder' => 'Escribe una dirección para ver la vista previa del mapa',
])

{{--
    Componente reutilizable: iframe de Google Maps a partir de texto libre.

    USO:
     <x-google-maps-embed :location="$direccion" class="w-full h-64 rounded-lg" />

    No requiere API key ni dependencias de terceros (Leaflet/Nominatim/OSM
    fueron eliminados de este módulo) — Google Maps resuelve la búsqueda del
    texto del lado del cliente vía el endpoint público "output=embed".

    SEGURIDAD:
     - App\Support\Maps\GoogleMapsEmbed::embedUrl() aplica trim() + urlencode()
       antes de construir la URL — ver esa clase para el detalle.
     - sandbox + referrerpolicy limitan la superficie de ataque del iframe.
     - title/aria-label usan {{ }} → escape XSS automático de Blade.
--}}
@php
    $mapaUrl = \App\Support\Maps\GoogleMapsEmbed::embedUrl($location, $zoom);
@endphp

@if ($mapaUrl)
    <iframe
        src="{{ $mapaUrl }}"
        {{ $attributes->merge(['class' => 'w-full h-64 rounded-lg border-0']) }}
        allowfullscreen
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        sandbox="allow-scripts allow-same-origin allow-popups"
        title="Mapa de ubicación — {{ $location }}"
        aria-label="Mapa de la ubicación: {{ $location }}"
    ></iframe>
@else
    <div
        {{ $attributes->merge(['class' => 'w-full h-64 rounded-lg border-2 border-dashed border-gray-200 bg-gray-50 flex items-center justify-center']) }}
    >
        <p class="text-sm text-gray-400 text-center px-4">{{ $placeholder }}</p>
    </div>
@endif
