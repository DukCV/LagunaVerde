{{--
    livewire/shared/impact-stats.blade.php
    ─────────────────────────────────────────────────────────────────────
    Grid de 3 estadísticas — sin margen/borde propio: cada página que lo
    use (Home, Quiénes Somos) lo envuelve con su propio espaciado.
    Pensado para texto blanco sobre fondo oscuro/degradado.

    wire:poll.30s en la raíz refresca el componente completo.
    ─────────────────────────────────────────────────────────────────────
--}}

<div wire:poll.30s="actualizar" class="grid grid-cols-3 gap-3 sm:gap-6">
    <div>
        <div class="text-2xl sm:text-3xl text-white mb-0.5 sm:mb-1 font-bold">
            {{ $voluntariosActivos }}+
        </div>
        <div class="text-white/80 text-xs sm:text-sm leading-snug">Voluntarios Activos</div>
    </div>

    <div>
        {{-- Dato fijo, sin cálculo --}}
        <div class="text-2xl sm:text-3xl text-white mb-0.5 sm:mb-1 font-bold">100%</div>
        <div class="text-white/80 text-xs sm:text-sm leading-snug">Comprometidos</div>
    </div>

    <div>
        <div class="text-2xl sm:text-3xl text-white mb-0.5 sm:mb-1 font-bold">
            {{ $colaboradores }}
        </div>
        <div class="text-white/80 text-xs sm:text-sm leading-snug">Colaboradores</div>
    </div>
</div>
