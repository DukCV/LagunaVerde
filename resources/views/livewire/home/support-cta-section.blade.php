{{--
    livewire/home/support-cta-section.blade.php
    ─────────────────────────────────────────────────────────────────────
    CTA del home: logo destacado + acción según sesión.

    Seguridad: auth()->user()->name se imprime con {{ }} → escape XSS
    automático de Blade, aunque el nombre contenga HTML/JS malicioso.
    ─────────────────────────────────────────────────────────────────────
--}}

<section class="relative py-20 bg-gradient-to-br from-blue-600 via-blue-700 to-emerald-600 overflow-hidden">

    {{-- Manchas de fondo decorativas — puramente visuales, sin contenido --}}
    <div class="absolute inset-0 opacity-10 pointer-events-none" aria-hidden="true">
        <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl translate-x-1/2 translate-y-1/2"></div>
    </div>

    <div class="container mx-auto px-6 relative z-10">
        <div class="max-w-2xl mx-auto flex flex-col items-center gap-8 text-center">

            {{-- Logo centrado con anillo glass y efecto hover --}}
            <div
                class="group w-28 h-28 sm:w-32 sm:h-32 bg-white/15 backdrop-blur-md rounded-full
                       flex items-center justify-center ring-1 ring-white/30 shadow-xl
                       transition-transform duration-300 hover:scale-105 hover:ring-white/50"
            >
                <img
                    src="{{ asset('img/LOGO_1.png') }}"
                    alt="Logo Consejo Ciudadano"
                    loading="lazy"
                    class="w-20 h-20 sm:w-24 sm:h-24 object-contain transition-transform duration-300 group-hover:rotate-6"
                >
            </div>

            <div>
                <h2 class="text-white text-3xl sm:text-4xl font-semibold mb-3 leading-tight">
                    Cuidemos juntos la Laguna Verde
                </h2>
                <p class="text-white/90 text-base sm:text-lg leading-relaxed">
                    Súmate a nuestra comunidad y forma parte del cambio.
                </p>
            </div>

            {{-- Acción según sesión --}}
            @auth
                {{-- Autenticado: agradecimiento personalizado --}}
                <p class="text-white text-xl sm:text-2xl font-semibold">
                    ¡Gracias por apoyarnos, {{ auth()->user()->name }}!
                </p>
            @else
                {{-- Invitado: abre el mismo modal de registro global --}}
                <button
                    type="button"
                    wire:click="abrirRegistro"
                    class="inline-flex items-center gap-2 px-8 py-4 bg-white text-blue-700
                           font-semibold rounded-xl shadow-xl hover:bg-blue-50
                           hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-200"
                >
                    Regístrate
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            @endauth

        </div>
    </div>
</section>
