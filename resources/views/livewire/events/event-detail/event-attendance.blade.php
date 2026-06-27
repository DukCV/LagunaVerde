{{--
    livewire/events/event-detail/event-attendance.blade.php
    ─────────────────────────────────────────────────────────────────────
    UI dirigida por estado (sin formulario): invitado, disponible,
    inscrito, cupo lleno o evento iniciado/finalizado.

    Seguridad: {{ }} escapa automáticamente — protección XSS. Los botones
    de acción real (toggleAttendance) solo se renderizan cuando el estado
    del servidor lo permite; el servidor revalida todo de cualquier forma.
    ─────────────────────────────────────────────────────────────────────
--}}

<div class="bg-white border-2 border-blue-500 rounded-2xl p-8">

    @guest
        {{-- ── Invitado: invita a autenticarse ──────────────────────────── --}}
        <h3 class="text-2xl font-semibold text-gray-900 mb-2">
            Confirmar Asistencia
        </h3>
        <p class="text-gray-600 text-sm mb-6 leading-relaxed">
            Completa el registro para reservar tu lugar en este evento.
        </p>

        <div class="flex flex-col sm:flex-row gap-3">
            <button
                type="button"
                wire:click="abrirLogin"
                class="flex-1 py-3 px-4 border-2 border-gray-300 text-gray-700 font-semibold
                       rounded-xl hover:bg-gray-50 transition-colors text-sm"
            >
                Iniciar sesión
            </button>
            <button
                type="button"
                wire:click="abrirRegistro"
                class="flex-1 py-3 px-4 bg-blue-600 text-white font-semibold rounded-xl
                       hover:bg-blue-700 transition-colors text-sm"
            >
                Regístrate
            </button>
        </div>

    @else
        {{-- ── Autenticado: estado según horario/cupo/inscripción ───────── --}}
        <h3 class="text-2xl font-semibold text-gray-900 mb-2">
            Confirmar Asistencia
        </h3>

        {{-- Aviso de resultado de la última acción --}}
        @if ($mensaje !== '')
            <div
                class="mb-4 p-3 rounded-xl text-sm font-medium
                       {{ $mensajeEsError ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' }}"
                role="alert"
            >
                {{ $mensaje }}
            </div>
        @endif

        @if ($eventoFinalizado || $eventoIniciado)
            {{-- ── Evento ya iniciado/finalizado: ambas acciones bloqueadas ── --}}
            <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                @if ($eventoFinalizado)
                    Este evento ya finalizó.
                @else
                    Este evento ya está en curso.
                @endif
            </p>
            <button
                type="button"
                disabled
                class="w-full py-4 bg-gray-200 text-gray-500 font-semibold rounded-xl
                       cursor-not-allowed flex items-center justify-center gap-2"
            >
                {{ $eventoFinalizado ? 'Evento finalizado' : 'Evento en curso' }}
            </button>

        @elseif ($estaInscrito)
            {{-- ── Inscrito y el evento aún no inicia: puede cancelar ───────── --}}
            <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                Ya tienes tu lugar reservado en este evento.
            </p>
            <button
                type="button"
                wire:click="toggleAttendance"
                wire:loading.attr="disabled"
                wire:target="toggleAttendance"
                class="w-full py-4 bg-white border-2 border-red-500 text-red-600 font-semibold
                       rounded-xl hover:bg-red-50 disabled:opacity-60 disabled:cursor-not-allowed
                       transition-colors flex items-center justify-center gap-2"
            >
                <svg wire:loading wire:target="toggleAttendance" class="w-5 h-5 animate-spin"
                     viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span wire:loading.remove wire:target="toggleAttendance">Cancelar Asistencia</span>
                <span wire:loading wire:target="toggleAttendance">Procesando…</span>
            </button>

        @elseif ($ocupados >= $capacidadTotal)
            {{-- ── Sin inscripción, cupo lleno: acción deshabilitada ────────── --}}
            <p class="text-gray-600 text-sm mb-4 leading-relaxed">
                Este evento alcanzó su capacidad máxima.
            </p>
            <button
                type="button"
                disabled
                class="w-full py-4 bg-gray-200 text-gray-500 font-semibold rounded-xl
                       cursor-not-allowed flex items-center justify-center gap-2"
            >
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full
                             bg-gray-300 text-gray-600 text-xs font-bold uppercase tracking-wide">
                    Cupo Lleno
                </span>
            </button>

        @else
            {{-- ── Disponible: puede confirmar ──────────────────────────────── --}}
            <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                Confirma tu lugar — quedan {{ $capacidadTotal - $ocupados }} de {{ $capacidadTotal }} lugares.
            </p>
            <button
                type="button"
                wire:click="toggleAttendance"
                wire:loading.attr="disabled"
                wire:target="toggleAttendance"
                class="w-full py-4 bg-blue-600 text-white font-semibold rounded-xl
                       hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed
                       transition-colors flex items-center justify-center gap-2"
            >
                <svg wire:loading wire:target="toggleAttendance" class="w-5 h-5 animate-spin"
                     viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <span wire:loading.remove wire:target="toggleAttendance">Confirmar Asistencia</span>
                <span wire:loading wire:target="toggleAttendance">Procesando…</span>
            </button>
        @endif
    @endguest

</div>
