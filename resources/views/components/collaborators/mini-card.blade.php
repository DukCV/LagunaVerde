@props(['partner'])

{{--
    components/collaborators/mini-card.blade.php
    ─────────────────────────────────────────────────────────────────────
    Tarjeta mínima para el slider infinito de "Nuestros Colaboradores"
    (home y Quiénes Somos) — solo logo, nombre y categoría.

    Mismo mapeo categoría→icono/color que <x-collaborators.card> (listado
    público /colaboradores), reducido a versión compacta — DRY visual.

    SEGURIDAD: {{ }} en toda salida → escape XSS automático de Blade;
    e() en el atributo src del logo → previene XSS en la URL de la imagen.
    ─────────────────────────────────────────────────────────────────────
--}}

@php
    $categoryConfig = match ($partner->type) {
        'Corporativo'   => ['icon' => '🏢', 'color' => 'bg-blue-100 text-blue-800'],
        'Educativo'     => ['icon' => '🎓', 'color' => 'bg-purple-100 text-purple-800'],
        'ONG'           => ['icon' => '🌍', 'color' => 'bg-green-100 text-green-800'],
        'Gubernamental' => ['icon' => '🏛️', 'color' => 'bg-gray-100 text-gray-800'],
        'Tecnológico'   => ['icon' => '💻', 'color' => 'bg-indigo-100 text-indigo-800'],
        'Fundación'     => ['icon' => '🤝', 'color' => 'bg-teal-100 text-teal-800'],
        'Comunitario'   => ['icon' => '👥', 'color' => 'bg-orange-100 text-orange-800'],
        'Persona'       => ['icon' => '👤', 'color' => 'bg-pink-100 text-pink-800'],
        default         => ['icon' => '🔖', 'color' => 'bg-gray-100 text-gray-800'],
    };
@endphp

<div class="shrink-0 w-36 sm:w-40 bg-white rounded-xl shadow-md hover:shadow-lg transition-shadow p-4 flex flex-col items-center text-center">

    {{-- Logo / ícono de respaldo según categoría --}}
    <div class="w-14 h-14 rounded-full bg-gray-50 flex items-center justify-center overflow-hidden mb-3 shrink-0">
        @if ($partner->logoUrl)
            <img
                src="{{ e($partner->logoUrl) }}"
                alt="Logo de {{ $partner->name }}"
                loading="lazy"
                class="w-full h-full object-cover"
            >
        @else
            <span class="text-2xl" aria-hidden="true">{{ $categoryConfig['icon'] }}</span>
        @endif
    </div>

    {{-- Nombre --}}
    <p class="text-sm font-semibold text-gray-900 truncate w-full" title="{{ $partner->name }}">
        {{ $partner->name }}
    </p>

    {{-- Categoría --}}
    <span class="mt-1.5 inline-block px-2 py-0.5 rounded-full text-[11px] font-medium truncate max-w-full {{ $categoryConfig['color'] }}">
        {{ $partner->type }}
    </span>

</div>
