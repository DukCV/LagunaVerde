<div class="space-y-6">

    {{-- Estado: mensaje enviado exitosamente --}}
    @if($success)
        <div class="bg-green-50 border-2 border-green-500 rounded-2xl p-8 text-center">
            <h3 class="text-2xl font-semibold text-gray-900 mb-2">
                ¡Mensaje enviado!
            </h3>

            <p class="text-gray-600">
                Gracias por contactarnos. Te responderemos pronto.
            </p>
        </div>
    @endif

    {{-- Aviso informativo del formulario --}}
    <div class="bg-blue-50 border-l-4 border-blue-600 p-4 rounded-r-lg text-gray-700 text-sm">
        ¿Tienes dudas o sugerencias? Envíanos un mensaje.
    </div>

    <form wire:submit.prevent="submit" class="space-y-5">

        {{-- Nombre --}}
        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700">
                Nombre <span class="text-red-500" aria-hidden="true">*</span>
            </label>

            <input
                type="text"
                wire:model.defer="name"
                maxlength="100"
                autocomplete="name"
                placeholder="Tu nombre completo"
                class="w-full border border-gray-300 bg-white text-gray-900 rounded-lg px-4 py-3
                       placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-transparent transition-all text-sm"
                aria-required="true"
            >

            @error('name')
                <span class="text-red-500 text-xs mt-1 block" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Email --}}
        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700">
                Correo electrónico <span class="text-red-500" aria-hidden="true">*</span>
            </label>

            <input
                type="email"
                wire:model.defer="email"
                maxlength="255"
                autocomplete="email"
                placeholder="tu@correo.com"
                class="w-full border border-gray-300 bg-white text-gray-900 rounded-lg px-4 py-3
                       placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-transparent transition-all text-sm"
                aria-required="true"
            >

            @error('email')
                <span class="text-red-500 text-xs mt-1 block" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Asunto --}}
        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700">
                Asunto <span class="text-red-500" aria-hidden="true">*</span>
            </label>

            <select
                wire:model.defer="subject"
                class="w-full border border-gray-300 bg-white text-gray-900 rounded-lg px-4 py-3
                       focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                       transition-all text-sm cursor-pointer appearance-none"
                aria-required="true"
            >
                <option value="" class="text-gray-400">Selecciona un asunto</option>

                @foreach($subjects as $item)
                    <option value="{{ $item }}" class="text-gray-900">{{ $item }}</option>
                @endforeach
            </select>

            @error('subject')
                <span class="text-red-500 text-xs mt-1 block" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Mensaje --}}
        <div>
            <label class="block mb-1.5 text-sm font-medium text-gray-700">
                Mensaje <span class="text-red-500" aria-hidden="true">*</span>
            </label>

            <textarea
                wire:model.defer="message"
                rows="6"
                maxlength="2000"
                placeholder="Escribe tu mensaje aquí..."
                class="w-full border border-gray-300 bg-white text-gray-900 rounded-lg px-4 py-3
                       placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500
                       focus:border-transparent transition-all text-sm resize-none"
                aria-required="true"
            ></textarea>

            @error('message')
                <span class="text-red-500 text-xs mt-1 block" role="alert">{{ $message }}</span>
            @enderror
        </div>

        {{-- Aviso de privacidad --}}
        <div class="flex gap-3 items-start">
            <input
                type="checkbox"
                wire:model="acceptPrivacy"
                id="privacy-check"
                class="mt-0.5 w-4 h-4 accent-blue-600 cursor-pointer shrink-0"
                aria-required="true"
            >

            <label for="privacy-check" class="text-sm text-gray-700 cursor-pointer leading-relaxed">
                Acepto el aviso de privacidad
                <span class="text-red-500" aria-hidden="true">*</span>
            </label>
        </div>

        @error('acceptPrivacy')
            <span class="text-red-500 text-xs block" role="alert">{{ $message }}</span>
        @enderror

        {{-- Enviar --}}
        <button
            type="submit"
            wire:loading.attr="disabled"
            class="w-full bg-blue-600 text-white font-semibold py-3.5 rounded-lg
                   hover:bg-blue-700 active:scale-[0.99] disabled:opacity-60
                   disabled:cursor-not-allowed transition-all duration-200 text-sm"
        >
            <span wire:loading.remove>Enviar mensaje</span>
            <span wire:loading class="inline-flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Enviando...
            </span>
        </button>

    </form>
</div>
