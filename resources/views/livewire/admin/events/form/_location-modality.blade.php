{{--
    Sección: Modalidad y Ubicación.

    UBICACIÓN COMO TEXTO LIBRE — SIN GEOCODIFICACIÓN:
     El campo 'location' es un input de texto simple que el administrador
     llena a mano (ej. "Muelle Principal, Laguna Norte, Sector A"). No hay
     geocodificación ni dependencias de terceros en este formulario: ese
     mismo texto se usa tal cual en la vista pública del evento para
     construir el iframe de Google Maps (ver event-detail-page.blade.php).

     VISTA PREVIA EN VIVO: wire:model.live.debounce.500ms sincroniza el
     texto con el servidor 500ms después de la última tecla — evita un
     round-trip por cada pulsación — y <x-google-maps-embed> reconstruye el
     iframe en cada render con el valor ya actualizado de $location->location.

     x-data solo guarda 'modality' en memoria del cliente para alternar las
     secciones de dirección/enlace virtual sin esperar un round-trip al
     servidor — Livewire sigue siendo la única fuente de verdad real.
--}}
<div
    x-data="{ modality: @js($location->modality) }"
    class="bg-white rounded-xl p-6 shadow-sm border border-gray-200"
>
    <div class="flex items-start gap-3 mb-5">
        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
            <x-admin-icon name="map-pin" class="w-4.5 h-4.5 text-blue-600" />
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Modalidad y Ubicación</h2>
            <p class="text-xs text-gray-500 mt-0.5">Define cómo y dónde se llevará a cabo el evento</p>
        </div>
    </div>

    {{-- Selector de modalidad --}}
    <div class="mb-5">
        <label for="form-modalidad" class="block text-sm font-medium text-gray-700 mb-1.5">
            Modalidad del evento <span class="text-red-500">*</span>
        </label>
        <div class="relative">
            <x-admin-icon name="globe-alt" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
            <select
                id="form-modalidad"
                wire:model="location.modality"
                x-on:change="modality = $event.target.value"
                class="w-full pl-9 pr-9 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                       @error('location.modality') border-red-400 bg-red-50 @enderror"
                aria-required="true"
            >
                <option value="">Seleccionar modalidad...</option>
                <option value="presencial">Presencial</option>
                <option value="virtual">Virtual</option>
                <option value="hibrido">Híbrido</option>
            </select>
            <x-admin-icon name="chevron-right"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 pointer-events-none rotate-90" />
        </div>
        @error('location.modality')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Ubicación presencial: dirección en texto libre --}}
    <div
        x-show="modality === 'presencial' || modality === 'hibrido'"
        x-cloak
        class="mb-5 space-y-2"
    >
        <template x-if="modality === 'hibrido'">
            <p class="text-xs text-blue-700 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 flex items-center gap-1.5">
                <x-admin-icon name="map-pin" class="w-3 h-3" />
                Ubicación presencial del componente híbrido
            </p>
        </template>

        <div>
            <label for="form-direccion" class="block text-sm font-medium text-gray-700 mb-1.5">
                Dirección / Lugar <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <x-admin-icon name="map-pin" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-500 pointer-events-none" />
                <input
                    type="text"
                    id="form-direccion"
                    wire:model.live.debounce.500ms="location.location"
                    placeholder="Ej: Muelle Principal, Laguna Norte, Sector A"
                    maxlength="300"
                    autocomplete="off"
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                           @error('location.location') border-red-400 bg-red-50 @enderror"
                >
            </div>
            @error('location.location')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-400 mt-1">
                Este texto se mostrará tal cual y se usará para ubicar el evento en Google Maps en la página pública.
            </p>

            {{-- Vista previa reactiva: se reconstruye con cada actualización debounced --}}
            <div wire:loading.class="opacity-50" wire:target="location.location" class="mt-3 transition-opacity">
                <x-google-maps-embed
                    :location="$location->location"
                    class="w-full h-48 rounded-lg border border-gray-200"
                />
            </div>
        </div>
    </div>

    {{-- Enlace virtual --}}
    <div x-show="modality === 'virtual' || modality === 'hibrido'" x-cloak :class="(modality === 'presencial' || modality === 'hibrido') ? 'border-t border-gray-100 pt-5' : ''">
        <template x-if="modality === 'hibrido'">
            <p class="text-xs text-purple-700 bg-purple-50 border border-purple-100 rounded-lg px-3 py-2 flex items-center gap-1.5 mb-3">
                <x-admin-icon name="link" class="w-3 h-3" />
                Enlace del componente virtual del evento híbrido
            </p>
        </template>

        <label for="form-enlace-virtual" class="block text-sm font-medium text-gray-700 mb-1.5">
            Enlace de la sala virtual / reunión <span class="text-red-500">*</span>
        </label>
        <div class="relative">
            <x-admin-icon name="link" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-purple-500 pointer-events-none" />
            <input
                type="url"
                id="form-enlace-virtual"
                wire:model="location.virtualLink"
                placeholder="https://meet.google.com/... o https://zoom.us/j/..."
                maxlength="500"
                class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                       @error('location.virtualLink') border-red-400 bg-red-50 @enderror"
            >
        </div>
        @error('location.virtualLink')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <p class="text-xs text-gray-400 mt-1">El enlace será visible para los asistentes registrados.</p>
    </div>

    {{-- Estado vacío: sin modalidad seleccionada --}}
    <div x-show="modality === ''" x-cloak class="border-2 border-dashed border-gray-200 rounded-xl p-8 text-center">
        <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <x-admin-icon name="globe-alt" class="w-5.5 h-5.5 text-gray-400" />
        </div>
        <p class="text-sm text-gray-500">Selecciona la modalidad para configurar la ubicación</p>
    </div>
</div>
