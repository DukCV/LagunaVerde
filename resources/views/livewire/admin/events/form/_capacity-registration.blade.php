{{--
    Sección: Capacidad e Inscripciones.

    Toggles 100% Alpine.js, pero la variable de cada uno NO es un booleano
    local: es una referencia $wire.entangle() — Alpine y el Form object de
    Livewire comparten la MISMA fuente de verdad. Esto evita por diseño el
    bug de desincronización anterior (estado local vs. estado del servidor
    desfasados por la latencia de red): no existe un "snapshot" que pueda
    quedar desactualizado, ni un $wire.set() manual que reescribir por cada
    toggle nuevo (ver x-admin-toggle).

    entangle() es DEFERRED (sin ".live"): el cambio se ve al instante en el
    DOM sin esperar al servidor, y el valor viaja a Livewire en la próxima
    petición ya existente (ej. al pulsar Guardar/Publicar) — cero peticiones
    de red adicionales por cada clic, importante en Hostinger donde cada
    round-trip pesa.
--}}
<div
    x-data="{
        unlimited: $wire.entangle('registration.unlimitedCapacity'),
        enabled: $wire.entangle('registration.registrationEnabled'),
        noEndDate: $wire.entangle('registration.registrationNoEndDate'),
    }"
    class="bg-white rounded-xl p-6 shadow-sm border border-gray-200"
>
    <div class="flex items-start gap-3 mb-5">
        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
            <x-admin-icon name="users" class="w-4.5 h-4.5 text-blue-600" />
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Capacidad e Inscripciones</h2>
            <p class="text-xs text-gray-500 mt-0.5">Define el aforo y las fechas de registro de asistentes</p>
        </div>
    </div>

    {{-- Capacidad máxima con toggle ilimitado --}}
    <div class="mb-6">
        <label for="form-capacidad" class="block text-sm font-medium text-gray-700 mb-1.5">
            Capacidad máxima de asistentes
        </label>
        <div class="flex items-center gap-4">
            <div class="relative flex-1">
                <x-admin-icon name="users" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                <input
                    type="number"
                    id="form-capacidad"
                    min="1"
                    placeholder="Ej: 200"
                    {{-- .live.debounce.500ms: igual que la vista previa del
                         mapa (_location-modality.blade.php) — sincroniza con
                         el servidor 500ms después de la última tecla, sin un
                         round-trip por pulsación, para que
                         RegistrationForm::updatedCapacityTotal() pueda
                         reiniciar a 0 un valor negativo de forma reactiva. --}}
                    wire:model.live.debounce.500ms="registration.capacityTotal"
                    :disabled="unlimited"
                    :class="unlimited ? 'opacity-50 cursor-not-allowed' : ''"
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-300 rounded-lg
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                           @error('registration.capacityTotal') border-red-400 bg-red-50 @enderror"
                >
            </div>
            <div
                class="flex items-center gap-2 px-4 py-2.5 rounded-lg border transition-colors flex-shrink-0"
                :class="unlimited ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'"
            >
                <x-admin-toggle model="unlimited" id="toggle-ilimitado" />
                {{--
                    Sin @click propio: <button> es un elemento "labelable", así
                    que el navegador ya reenvía el click de este <label> al
                    botón vía el atributo "for" (activación nativa). Un
                    @click duplicado aquí disparaba el toggle dos veces (uno
                    manual + uno nativo). Con "unlimited" entangled, un doble
                    disparo ya ni siquiera sería visible (vuelve a su mismo
                    valor), pero se evita igual por limpieza.
                --}}
                <label
                    for="toggle-ilimitado"
                    class="text-sm whitespace-nowrap cursor-pointer select-none"
                    :class="unlimited ? 'text-blue-700' : 'text-gray-600'"
                >
                    Ilimitado
                </label>
            </div>
        </div>
        @error('registration.capacityTotal')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <p x-show="unlimited" x-cloak class="text-xs text-blue-600 mt-1.5 bg-blue-50 px-3 py-2 rounded-lg">
            El evento no tendrá límite de asistentes.
        </p>
    </div>

    {{-- Habilitar fechas de inscripción --}}
    <div
        class="border rounded-xl transition-colors"
        :class="enabled ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200 bg-gray-50/50'"
    >
        <div class="flex items-center justify-between p-4">
            <div>
                <p class="text-sm text-gray-800">Habilitar fechas de inscripción</p>
                <p class="text-xs text-gray-500 mt-0.5">Controla el período en que los asistentes pueden registrarse</p>
            </div>
            <x-admin-toggle model="enabled" id="toggle-inscripcion" />
        </div>

        <div x-show="enabled" x-cloak class="px-4 pb-4 space-y-4 border-t border-blue-100 pt-4">

            {{-- Inicio de inscripción --}}
            <div>
                <x-inputs.date-picker
                    type="date"
                    id="form-reg-inicio"
                    model="registration.registrationStartAt"
                    label="Inicio de inscripción"
                    required
                />
                <p class="text-xs text-gray-400 mt-1">Debe abrir al menos 1 día antes del inicio del evento.</p>
            </div>

            {{-- Toggle "Sin fecha de fin de inscripción" --}}
            <div
                class="border rounded-xl p-3 transition-colors"
                :class="noEndDate ? 'border-amber-200 bg-amber-50/60' : 'border-gray-200 bg-white'"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-800">Sin fecha de fin de inscripción</p>
                        <p
                            class="text-xs text-gray-500 mt-0.5"
                            x-text="noEndDate
                                ? 'Se usará la fecha de inicio del evento como límite.'
                                : 'Define manualmente la fecha de cierre de inscripciones.'"
                        ></p>
                    </div>
                    <x-admin-toggle model="noEndDate" id="toggle-sin-fin" />
                </div>
                <div
                    x-show="noEndDate"
                    x-cloak
                    class="mt-2 flex items-center gap-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"
                >
                    <x-admin-icon name="exclamation-triangle" class="w-3.5 h-3.5 flex-shrink-0" />
                    La fecha de inicio del evento se utilizará como fecha límite de registro.
                </div>
            </div>

            {{-- Fin de inscripción (condicional) --}}
            <div x-show="!noEndDate" x-cloak>
                <x-inputs.date-picker
                    type="date"
                    id="form-reg-fin"
                    model="registration.registrationEndAt"
                    label="Fin de inscripción"
                    required
                />
                <p class="text-xs text-gray-400 mt-1">Debe ser posterior al inicio de inscripción; puede coincidir con el inicio del evento, pero no superarlo.</p>
            </div>
        </div>
    </div>
</div>
