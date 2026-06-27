{{--
    Sección: Información General — título, categoría, portada, cuerpo
    (Trix) y descripción breve del evento, en ese orden.

    ORDEN DE CAMPOS: "Descripción breve" va DESPUÉS del editor Trix a
    propósito — el resumen corto de las tarjetas del listado se redacta
    una vez escrito el cuerpo completo, así que este orden sigue el flujo
    natural de llenado del formulario.
--}}
<div class="bg-white rounded-xl p-6 shadow-sm border border-gray-200 space-y-5">

    <div class="flex items-start gap-3 mb-1">
        <div class="w-9 h-9 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center flex-shrink-0">
            <x-admin-icon name="tag" class="w-4.5 h-4.5 text-blue-600" />
        </div>
        <div>
            <h2 class="text-sm font-semibold text-gray-900">Información General</h2>
            <p class="text-xs text-gray-500 mt-0.5">Datos principales que identifican el evento</p>
        </div>
    </div>

    {{-- Título --}}
    <div>
        <label for="form-titulo" class="block text-sm font-medium text-gray-700 mb-1.5">
            Título del evento <span class="text-red-500">*</span>
        </label>
        <input
            type="text"
            id="form-titulo"
            wire:model="generalInfo.name"
            placeholder="Ej: Jornada de Limpieza Comunitaria en la Laguna"
            maxlength="180"
            autocomplete="off"
            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                   @error('generalInfo.name') border-red-400 bg-red-50 @enderror"
            aria-required="true"
        >
        @error('generalInfo.name')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Categoría --}}
    <div>
        <label for="form-categoria" class="block text-sm font-medium text-gray-700 mb-1.5">
            Categoría <span class="text-red-500">*</span>
        </label>
        <div class="relative">
            <select
                id="form-categoria"
                wire:model="generalInfo.categoryId"
                class="w-full appearance-none px-4 py-2.5 text-sm border border-gray-300 rounded-lg bg-white text-gray-900
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all pr-9
                       @error('generalInfo.categoryId') border-red-400 bg-red-50 @enderror"
                aria-required="true"
            >
                <option value="">— Selecciona una categoría —</option>
                @foreach ($categorias as $id => $nombre)
                    <option value="{{ $id }}" @selected($generalInfo->categoryId == $id)>
                        {{ $nombre }}
                    </option>
                @endforeach
            </select>
            <x-admin-icon name="chevron-down"
                class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
        </div>
        @error('generalInfo.categoryId')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Foto de portada --}}
    <div>
        <p class="text-sm font-medium text-gray-700 mb-3">
            Foto de portada <span class="text-red-500">*</span>
        </p>

        {{--
            isPreviewable() evita la excepción de Livewire cuando el archivo
            seleccionado no es un formato previsualizable (el <input> solo
            sugiere "accept" en el cliente — no es una barrera real: un
            usuario puede igualmente elegir "todos los archivos"). El
            archivo inválido simplemente no muestra miniatura aquí; la
            regla 'image' en reglasArchivos() lo rechaza al guardar.
        --}}
        @php
            $previewUrlPortada = match (true) {
                $coverImage !== null => $coverImage->isPreviewable() ? $coverImage->temporaryUrl() : null,
                $coverUrl && ! $removeCover => $coverUrl,
                default => null,
            };
        @endphp

        @if ($previewUrlPortada)
            <div class="relative rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-gray-50">
                <img
                    src="{{ $previewUrlPortada }}"
                    alt="Vista previa de la portada"
                    class="w-full max-h-72 object-contain"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                <div class="absolute bottom-3 right-3 flex gap-2">
                    <label
                        for="input-portada"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs bg-white/90 hover:bg-white text-gray-800 rounded-lg transition-colors backdrop-blur-sm cursor-pointer"
                    >
                        <x-admin-icon name="cloud-arrow-up" class="w-3.5 h-3.5" />
                        Cambiar imagen
                    </label>
                    <button
                        type="button"
                        wire:click="toggleEliminarPortada"
                        class="p-1.5 bg-white/90 hover:bg-white text-red-600 rounded-lg transition-colors backdrop-blur-sm"
                        aria-label="Quitar portada"
                    >
                        <x-admin-icon name="trash" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
        @else
            <label
                for="input-portada"
                class="block border-2 border-dashed border-gray-200 hover:border-blue-400 rounded-xl p-10 text-center cursor-pointer transition-colors bg-gray-50 hover:bg-blue-50/30 group"
            >
                <div class="w-14 h-14 rounded-2xl bg-blue-50 group-hover:bg-blue-100 flex items-center justify-center mx-auto mb-3 transition-colors">
                    <x-admin-icon name="photo" class="w-6 h-6 text-blue-400" />
                </div>
                <p class="text-sm text-gray-700 mb-1">Arrastra una imagen o haz clic para seleccionar</p>
                <p class="text-xs text-gray-400">JPG, PNG, WebP — máx. 5 MB</p>
            </label>
        @endif

        <input
            type="file"
            id="input-portada"
            wire:model="coverImage"
            accept="image/jpeg,image/png,image/webp"
            class="hidden"
            aria-label="Seleccionar imagen de portada"
        >

        <div wire:loading wire:target="coverImage" class="mt-2 flex items-center gap-2 text-xs text-blue-600">
            <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span>Subiendo imagen...</span>
        </div>

        @error('coverImage')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Cuerpo del evento (Trix) --}}
    <div class="pt-4 border-t border-gray-100">
        <p class="text-sm font-medium text-gray-700 mb-3">
            Descripción del Evento <span class="text-red-500">*</span>
        </p>

        {{--
            wire:ignore previene que Livewire toque el DOM del editor en
            re-renders. x-on:trix-file-accept previene adjuntos de archivo
            inline: las imágenes del evento se gestionan en la galería
            multimedia, no dentro del cuerpo del texto.
        --}}
        <div
            wire:ignore
            x-init="document.getElementById('contenido-editor-evento').setAttribute('placeholder', 'Describe el evento con todos los detalles: programa, ponentes, actividades, recomendaciones...')"
            x-on:trix-file-accept="$event.preventDefault()"
            class="@error('generalInfo.content') ring-1 ring-red-400 rounded-lg @enderror"
        >
            <x-rich-text::trix
                id="contenido-editor-evento"
                name="generalInfo.content"
                wire:model="generalInfo.content"
                :value="$generalInfo->content"
            />
        </div>

        @error('generalInfo.content')
            <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{--
        Descripción breve: reubicada justo debajo de "Descripción del
        Evento" (Trix) — el resumen corto que va en las tarjetas del
        listado se redacta DESPUÉS de escribir el cuerpo completo, así que
        este orden sigue el flujo natural de llenado del formulario.
        space-y-5 del contenedor padre ya aplica el espaciado vertical por
        posición (no por campo individual): mover este bloque no requiere
        tocar ningún margen, ni aquí ni en los campos vecinos.
    --}}
    <div>
        <label for="form-descripcion" class="block text-sm font-medium text-gray-700 mb-1.5">
            Descripción breve <span class="text-red-500">*</span>
        </label>
        <textarea
            id="form-descripcion"
            wire:model="generalInfo.description"
            placeholder="Resumen corto que aparece en las tarjetas del listado..."
            maxlength="500"
            rows="3"
            class="w-full px-4 py-2.5 text-sm border border-gray-300 rounded-lg resize-y
                   focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                   @error('generalInfo.description') border-red-400 bg-red-50 @enderror"
        ></textarea>
        @error('generalInfo.description')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

</div>
