<section class="py-20 bg-gradient-to-r from-blue-600 to-emerald-600 relative overflow-hidden">

    {{-- BACKGROUND BLOBS --}}

    <div class="absolute inset-0 opacity-10 pointer-events-none">

        <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2">
        </div>

        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl translate-x-1/2 translate-y-1/2">
        </div>

    </div>

    <div class="container mx-auto px-6 relative z-10">

        <div class="max-w-4xl mx-auto text-center">

            {{-- ICON --}}

            <div
                class="w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-8">

                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-white" fill="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        d="M12 21s-6.716-4.37-9.428-7.083C.859 12.205 0 10.487 0 8.615 0 5.477 2.477 3 5.615 3c1.872 0 3.59.859 4.885 2.243C11.795 3.859 13.513 3 15.385 3 18.523 3 21 5.477 21 8.615c0 1.872-.859 3.59-2.572 5.302C18.716 16.63 12 21 12 21z" />
                </svg>

            </div>

            {{-- HEADING --}}

            <h2 class="text-white text-4xl lg:text-5xl font-semibold mb-6 leading-tight">
                ¿Quieres Ser Parte de la Solución?
            </h2>

            <p class="text-white/90 text-lg md:text-xl mb-12 max-w-2xl mx-auto leading-relaxed">
                Tu apoyo es fundamental para proteger y restaurar nuestra laguna.
                Existen muchas formas de ayudar a generar un impacto real.
            </p>

            {{-- CTA BUTTONS --}}

            <div class="flex flex-col sm:flex-row gap-4 justify-center">

                {{-- Hacer una Donación (desactivado temporalmente) --}}
                {{--
                <a href="{{ $donateUrl }}"
                    class="group flex items-center justify-center gap-3 px-7 py-3.5 bg-white text-blue-600 rounded-xl hover:bg-blue-50 transition-all shadow-xl hover:shadow-white/40 hover:-translate-y-0.5">

                    <span class="text-lg font-medium">
                        Hacer una Donación
                    </span>

                    <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>

                </a>
                --}}

                @auth
                    {{-- Autenticado: agradecimiento personalizado --}}
                    <p class="text-white text-lg font-semibold">
                        ¡Gracias por apoyarnos, {{ auth()->user()->name }}!
                    </p>
                @else
                    {{-- Invitado: abre el mismo modal de registro global --}}
                    <button
                        type="button"
                        wire:click="abrirRegistro"
                        class="group flex items-center justify-center gap-3 px-7 py-3.5 bg-white/10 backdrop-blur-sm text-white border border-white/30 rounded-xl hover:bg-white/20 transition"
                    >
                        <span class="text-lg font-medium">
                            Registrarse
                        </span>

                        <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                @endauth

            </div>

            {{-- ESTADÍSTICAS DE IMPACTO EN TIEMPO REAL --}}

            <div class="mt-16 pt-12 border-t border-white/30 max-w-3xl mx-auto">
                <livewire:shared.impact-stats />
            </div>

        </div>

    </div>

</section>
