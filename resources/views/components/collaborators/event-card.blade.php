{{--
    components/collaborators/event-card.blade.php
    ─────────────────────────────────────────────────────────────────────
    Tarjeta compacta de colaborador para la grilla de la página pública de
    detalle de evento. Distinta de <x-collaborators.card> (perfil completo
    del socio en /colaboradores): aquí solo se muestran 3 datos — logo,
    nombre y su participación en ESTE evento — pensada para una grilla
    densa, no para una ficha de presentación del socio.

    Props:
      - collaborator (array{key:string, name:string, logoUrl:?string, participationDetails:?string})
        Ya resuelto y saneado por EventDetailDto::resolverColaboradores().

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida de texto → escape XSS automático de Blade.
    • e() en el atributo src del logo → previene XSS en la URL de la imagen.
    • $collaborator viene de un DTO de solo lectura: no expone partner_id
      ni ninguna otra clave que permita enumerar registros internos.
    ─────────────────────────────────────────────────────────────────────
--}}

@props(['collaborator'])

<article class="flex flex-col items-center text-center gap-2 p-3 bg-white rounded-xl border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all">

    {{-- ── Logo circular ───────────────────────────────────────────── --}}
    <div class="w-14 h-14 rounded-full overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center shrink-0">
        @if ($collaborator['logoUrl'])
            <img
                src="{{ e($collaborator['logoUrl']) }}"
                alt="Logo de {{ $collaborator['name'] }}"
                class="w-full h-full object-cover"
                loading="lazy"
                width="56"
                height="56"
            >
        @else
            {{-- Ícono de respaldo cuando el colaborador no tiene logo registrado --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3.75 21h16.5M4.5 21V4.5a.75.75 0 0 1 .75-.75h9a.75.75 0 0 1 .75.75V21M9 8.25h1.5m-1.5 3h1.5m-1.5 3h1.5m4.5-6h1.5m-1.5 3h1.5m-1.5 3h1.5" />
            </svg>
        @endif
    </div>

    {{-- ── Nombre ───────────────────────────────────────────────────── --}}
    <p class="text-sm font-medium text-gray-900 line-clamp-2 leading-snug" title="{{ $collaborator['name'] }}">
        {{ $collaborator['name'] }}
    </p>

    {{-- ── Participación (opcional) ────────────────────────────────── --}}
    @if ($collaborator['participationDetails'])
        <p class="text-xs text-gray-500 line-clamp-2 leading-snug">
            {{ $collaborator['participationDetails'] }}
        </p>
    @endif
</article>
