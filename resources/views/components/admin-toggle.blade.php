@props(['model', 'id' => null])
{{--
    Interruptor (toggle switch) reutilizable, 100% Alpine.js — sin round-trip
    al servidor para el efecto visual (cumple "zero-latency UI updates").

    Uso: <x-admin-toggle model="unlimited" id="..." />
     - $model: nombre de la variable en el x-data del contenedor padre. DEBE
       ser una referencia $wire.entangle('ruta.de.la.propiedad'), no un
       booleano plano (@js(...)) — entangle() es la única fuente de verdad
       compartida entre Alpine y Livewire, así que basta con reasignarla
       aquí: no se necesita (ni se debe duplicar) un $wire.set() manual.
--}}
<button
    type="button"
    @if($id) id="{{ $id }}" @endif
    role="switch"
    @click="{{ $model }} = !{{ $model }}"
    :aria-checked="{{ $model }}"
    class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500/30 cursor-pointer"
    :class="{{ $model }} ? 'bg-blue-600' : 'bg-gray-200'"
>
    <span
        class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform transition-transform duration-200 ease-in-out"
        :class="{{ $model }} ? 'translate-x-5' : 'translate-x-0'"
    ></span>
</button>
