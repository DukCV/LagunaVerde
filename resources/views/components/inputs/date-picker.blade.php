@props([
    'type' => 'date',
    'id',
    'model',
    'label' => null,
    'required' => false,
    'min' => null,
    'iconColor' => 'text-gray-400',
])

{{--
    Campo de fecha/hora reutilizable con selector nativo asistido por botón.

    Uso: <x-inputs.date-picker type="datetime-local" id="form-inicio"
                                model="schedule.startAt" label="Inicio del evento" required />

     - $model: ruta de la propiedad Livewire (wire:model) y también la clave
       usada en @error(), para que ambos queden siempre sincronizados.
     - Mismo patrón ya usado en el formulario de Noticias (ver
       resources/css/app.css → .admin-date-input): se oculta el indicador
       nativo de Chrome/Edge/Safari con CSS y se sustituye por un botón
       propio que llama a showPicker(). El operador "?." evita un error si
       el navegador no lo soporta (ej. Safari antiguo); en ese caso el
       campo sigue siendo editable a mano, sin JS adicional ni librerías
       de terceros — clave en el entorno con recursos limitados de Hostinger.
     - x-data (vacío) solo crea un scope propio de Alpine para que $refs no
       choque entre varias instancias de este componente en la misma vista.
--}}
<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-gray-700 mb-1.5">
            {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div x-data class="relative">
        <x-admin-icon
            name="{{ $type === 'date' ? 'calendar-days' : 'clock' }}"
            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 {{ $iconColor }} pointer-events-none"
        />

        <input
            type="{{ $type }}"
            id="{{ $id }}"
            x-ref="campo"
            wire:model="{{ $model }}"
            @if ($min) min="{{ $min }}" @endif
            @if ($required) aria-required="true" @endif
            class="admin-date-input w-full pl-9 pr-9 py-2.5 text-sm border border-gray-300 rounded-lg
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                   @error($model) border-red-400 bg-red-50 @enderror"
        >

        {{-- Botón disparador: misma posición/tamaño que el del formulario de Noticias --}}
        <button
            type="button"
            @click="$refs.campo.showPicker?.()"
            tabindex="-1"
            aria-label="Abrir selector de {{ $type === 'date' ? 'fecha' : 'fecha y hora' }}"
            class="absolute inset-y-0 right-1.5 flex items-center px-1.5 text-gray-400 hover:text-blue-600 rounded transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
            <x-admin-icon name="{{ $type === 'date' ? 'calendar-days' : 'clock' }}" class="w-4 h-4" />
        </button>
    </div>

    @error($model)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
