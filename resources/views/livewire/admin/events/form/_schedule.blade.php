{{--
    Sección: Programación — fecha de publicación, inicio y fin del evento.
--}}
<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200">

    <div class="flex items-start gap-3 mb-5">
        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
            <x-admin-icon name="calendar-days" class="w-4.5 h-4.5 text-blue-600" />
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Programación</h2>
            <p class="text-xs text-gray-500 mt-0.5">Fechas de publicación, inicio y fin del evento</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        {{-- Fecha de publicación --}}
        <div class="sm:col-span-2">
            {{-- min es solo una ayuda visual del navegador (deshabilita días
                 pasados en el selector nativo) — la regla real
                 after_or_equal:today se valida en el servidor, ver
                 ScheduleForm::rulesArchivos(). --}}
            <x-inputs.date-picker
                type="date"
                id="form-fecha-publicacion"
                model="schedule.publishedAt"
                label="Fecha de publicación"
                required
                min="{{ now()->format('Y-m-d') }}"
            />
        </div>

        {{-- Inicio --}}
        <div>
            <x-inputs.date-picker
                type="datetime-local"
                id="form-inicio"
                model="schedule.startAt"
                label="Inicio del evento"
                required
                icon-color="text-blue-400"
            />
        </div>

        {{-- Fin --}}
        <div>
            <x-inputs.date-picker
                type="datetime-local"
                id="form-fin"
                model="schedule.endAt"
                label="Fin del evento"
                required
                icon-color="text-purple-400"
            />
        </div>

        {{--
            Ayuda visual de la regla de negocio (ScheduleForm::validateEndAt()):
            mismo día calendario → mínimo 1 hora de diferencia; día posterior
            (evento nocturno o de varios días) → sin ese mínimo.
        --}}
        <p class="sm:col-span-2 text-xs text-gray-400 flex items-start gap-1.5">
            <x-admin-icon name="information-circle" class="w-3.5 h-3.5 flex-shrink-0 mt-px" />
            Si el evento termina el mismo día, el fin debe ser al menos 1 hora después del inicio.
            Para eventos nocturnos o de varios días, la hora de fin puede ser menor (ej. inicia 20:00, termina 02:00 del día siguiente).
        </p>
    </div>
</div>
