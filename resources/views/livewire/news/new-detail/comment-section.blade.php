{{--
    livewire/news/new-detail/comment-section.blade.php
    ────────────────────────────────────────────────────────────────────
    Recibe: $comments (array[]), $body, $showSuccess, auth()

    SEGURIDAD:
    • {{ }} en toda salida de usuario → XSS imposible.
    • {!! !!} nunca se usa en este componente.
    • Formulario solo visible a usuarios autenticados (@auth).
    • novalidate en el form → validación ocurre en PHP, no en el browser.
    • wire:poll.15s reduce la carga al servidor respecto al 8s anterior.

    RENDIMIENTO:
    • wire:target="refreshComments" scope los loading states al método poll,
      evitando parpadeos durante otras acciones (submitComment).
    • wire:loading.delay.short previene flicker en conexiones rápidas.
    ────────────────────────────────────────────────────────────────────
--}}

<div
    class="mt-16 pt-12 border-t border-gray-200"
    x-data="{ charCount: {{ strlen($body) }} }"
    x-on:hide-success.window="setTimeout(() => $wire.hideSuccess(), 5000)"
    wire:poll.15s="refreshComments"
>
    {{-- ── Cabecera ──────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8 flex-wrap gap-3">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">
            Comentarios
            @if (! empty($comments))
                <span class="text-xl font-normal text-gray-400">({{ count($comments) }})</span>
            @endif
        </h2>

        {{-- Indicador de actualización automática --}}
        <div
            wire:loading.delay.short
            wire:target="refreshComments"
            class="flex items-center gap-1.5 text-xs text-blue-500"
            aria-live="polite"
            aria-label="Actualizando comentarios"
        >
            <svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg"
                 fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10"
                        stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
            </svg>
            <span>Actualizando…</span>
        </div>
    </div>

    {{-- ── Lista de comentarios ──────────────────────────────────────── --}}
    <div
        class="space-y-4 sm:space-y-6 mb-12 transition-opacity duration-200"
        wire:loading.class="opacity-50"
        wire:target="refreshComments"
    >
        @forelse ($comments as $comment)
            <div class="flex gap-3 sm:gap-4" wire:key="comment-{{ $comment['id'] }}">
                {{-- Avatar --}}
                <div class="shrink-0" aria-hidden="true">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-blue-100 rounded-full
                                flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                </div>

                {{-- Cuerpo del comentario --}}
                <div class="flex-1 bg-gray-50 rounded-2xl p-4 sm:p-5 min-w-0">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-2">
                        <span class="font-semibold text-gray-900 truncate">
                            {{ $comment['authorName'] }}
                        </span>
                        <span aria-hidden="true" class="text-gray-300 hidden sm:inline">&bull;</span>
                        <time
                            datetime="{{ $comment['publishedAtIso'] }}"
                            class="text-gray-500 text-xs sm:text-sm"
                        >
                            {{ $comment['publishedAt'] }}
                        </time>
                    </div>
                    {{-- body pasa por strip_tags() en el DTO → no se necesita {!! !!} --}}
                    <p class="text-gray-700 leading-relaxed text-sm sm:text-base wrap-break-word">
                        {{ $comment['body'] }}
                    </p>
                </div>
            </div>
        @empty
            <div class="py-10 text-center">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-10 h-10 text-gray-200 mx-auto mb-3"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                     stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8 10h.01M12 10h.01M16 10h.01M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2v10z"/>
                </svg>
                <p class="text-gray-500 text-sm">Sé el primero en dejar un comentario.</p>
            </div>
        @endforelse
    </div>

    {{-- ── Panel de formulario / login ──────────────────────────────── --}}
    <div class="bg-gray-50 rounded-2xl p-5 sm:p-8">

        @auth
            {{-- ── Usuario autenticado: formulario ──────────────────── --}}
            <h3 class="text-lg sm:text-xl font-semibold mb-5 text-gray-900">
                Comentar como
                <span class="text-blue-600">{{ auth()->user()->name }}</span>
            </h3>

            {{-- Banner de éxito (auto-oculto via Alpine después de 5 s) --}}
            @if ($showSuccess)
                <div
                    class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg"
                    role="alert"
                    x-init="$dispatch('hide-success')"
                >
                    <p class="text-green-800 text-sm font-medium">
                        Tu comentario ha sido publicado correctamente.
                    </p>
                </div>
            @endif

            <form
                wire:submit="submitComment"
                class="space-y-4"
                novalidate
            >
                {{-- Textarea con contador de caracteres en tiempo real --}}
                <div>
                    <label for="comment-body"
                           class="block text-sm font-medium text-gray-700 mb-1.5">
                        Tu comentario
                        <span class="text-red-500" aria-hidden="true">*</span>
                    </label>

                    <textarea
                        id="comment-body"
                        wire:model="body"
                        x-on:input="charCount = $el.value.length"
                        rows="5"
                        maxlength="1000"
                        placeholder="Comparte tu opinión o pregunta…"
                        aria-describedby="body-error body-counter"
                        @class([
                            'w-full px-4 py-3 bg-white border rounded-lg text-sm',
                            'focus:ring-2 focus:ring-blue-500 focus:border-transparent',
                            'transition-colors resize-none',
                            'border-red-400 ring-1 ring-red-300' => $errors->has('body'),
                            'border-gray-300'                    => ! $errors->has('body'),
                        ])
                    ></textarea>

                    <div class="flex justify-between items-start mt-1 gap-2">
                        <div id="body-error" role="alert">
                            @error('body')
                                <p class="text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <p
                            id="body-counter"
                            class="text-xs shrink-0"
                            :class="charCount >= 1000 ? 'text-red-500 font-medium' :
                                    charCount >= 900  ? 'text-amber-500' : 'text-gray-400'"
                            aria-live="polite"
                        >
                            <span x-text="charCount"></span>/1000
                        </p>
                    </div>
                </div>

                {{-- Botón enviar con estado de carga --}}
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="submitComment"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600
                               text-white text-sm font-medium rounded-lg
                               hover:bg-blue-700 disabled:opacity-60
                               transition-colors focus:outline-none
                               focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        {{-- Ícono send —— visible cuando no está cargando --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor"
                             stroke-width="2"
                             wire:loading.remove wire:target="submitComment"
                             aria-hidden="true">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                        {{-- Spinner —— visible solo durante el submit --}}
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="w-4 h-4 animate-spin"
                             wire:loading wire:target="submitComment"
                             viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>

                        <span wire:loading.remove wire:target="submitComment">
                            Publicar comentario
                        </span>
                        <span wire:loading wire:target="submitComment">
                            Publicando…
                        </span>
                    </button>

                    <p class="text-xs text-gray-400">Los comentarios son públicos.</p>
                </div>
            </form>

        @else
            {{-- ── Usuario no autenticado: prompt de login ──────────── --}}
            <div class="text-center py-6 sm:py-8">
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-12 h-12 text-gray-200 mx-auto mb-4"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor"
                     stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                    <circle cx="12" cy="7" r="4"/>
                </svg>
                <h3 class="text-lg sm:text-xl font-semibold text-gray-900 mb-2">
                    Inicia sesión para comentar
                </h3>
                <p class="text-gray-500 mb-6 text-sm max-w-xs mx-auto">
                    Necesitas una cuenta para participar en la conversación.
                </p>
                <div class="flex justify-center flex-wrap gap-3">
                    <a
                        href="{{ route('login') }}"
                        class="px-5 py-2.5 bg-blue-600 text-white text-sm font-medium
                               rounded-lg hover:bg-blue-700 transition-colors
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Iniciar sesión
                    </a>
                    <a
                        href="{{ route('register') }}"
                        class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700
                               text-sm font-medium rounded-lg hover:bg-gray-50
                               transition-colors focus:outline-none
                               focus:ring-2 focus:ring-gray-300 focus:ring-offset-2"
                    >
                        Registrarme
                    </a>
                </div>
            </div>
        @endauth

    </div>
</div>
