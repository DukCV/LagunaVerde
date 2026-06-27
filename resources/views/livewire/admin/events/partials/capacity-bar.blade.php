{{--
    Partial: Barra de capacidad / aforo de un evento.

    Variables recibidas (todas ya calculadas en AdminEventItemDto — esta
    vista NO repite ninguna fórmula de negocio, solo decide qué pintar):
      $registrations : int  — inscritos activos (registered + waitlist)
      $capacityTotal : int  — valor crudo de la BD (0 = ilimitado)
      $isUnlimited   : bool
      $occupancyPct  : int  — 0..100, 0 cuando es ilimitado
      $isFull        : bool — false cuando es ilimitado
      $isAlmostFull  : bool — >= 80% de ocupación, sin estar lleno

    SEGURIDAD: todos los valores son numéricos/booleanos — sin riesgo XSS,
    pero igual se renderizan con {{ }} por consistencia con el resto del panel.
--}}
@if($isUnlimited)
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs text-gray-500">Inscritos</span>
            <div class="flex items-center gap-1 text-xs text-gray-700">
                <span>{{ number_format($registrations) }}</span>
                <span class="text-gray-400">/</span>
                <span class="text-gray-400" aria-label="Sin límite">∞</span>
            </div>
        </div>
        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-r from-blue-400 to-blue-600" style="width: 100%; opacity: 0.5"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1">Capacidad ilimitada</p>
    </div>
@else
    @php
        $barColor = $isFull ? 'bg-red-500' : ($isAlmostFull ? 'bg-amber-500' : 'bg-emerald-500');
        $textColor = $isFull ? 'text-red-600' : ($isAlmostFull ? 'text-amber-600' : 'text-gray-700');
    @endphp
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs text-gray-500">Capacidad</span>
            <span class="text-xs font-medium {{ $textColor }}">
                {{ number_format($registrations) }} / {{ number_format($capacityTotal) }}
            </span>
        </div>
        <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
            <div class="h-full rounded-full transition-all duration-500 {{ $barColor }}" style="width: {{ $occupancyPct }}%"></div>
        </div>
        <div class="flex items-center justify-between mt-1">
            <span class="text-xs text-gray-400">{{ $occupancyPct }}% ocupado</span>
            @if($isFull)
                <span class="text-xs text-red-600 font-medium">Lleno</span>
            @elseif($isAlmostFull)
                <span class="text-xs text-amber-600 font-medium">Casi lleno</span>
            @endif
        </div>
    </div>
@endif
