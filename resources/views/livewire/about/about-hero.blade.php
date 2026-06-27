<section id="identidad" class="py-20 bg-gradient-to-b from-white to-gray-50">
    <div class="container mx-auto px-4">

        {{-- Title --}}
        <div class="max-w-6xl mx-auto mb-16">
            <div class="text-center mb-12">
                <h1 class="text-gray-900 text-5xl lg:text-6xl mb-6">
                    Quiénes Somos
                </h1>

                <p class="text-gray-600 text-xl max-w-3xl mx-auto leading-relaxed">
                    Somos un Consejo Ciudadano conformado en octubre del 2023 tras la manifestación de la ciudadanía por
                    el estado ecológico de la Laguna de Chignahuapan donde el H. Ayuntamiento 2021- 2024 hizo el
                    nombramiento y toma de protesta a un total de 14 consejeros y consejeras ciudadanos quienes a través
                    de éste año hemos vinculado, gestionado y accionado en diferentes mesas de trabajo. Actualmente nos
                    encontramos enfocados desarrollando las siguientes iniciativas con diferentes agrupaciones,
                    colectivos, asociaciones civiles, universidades, instancias gubernamentales y, ciudadanía civil
                    organizada.
                </p>
            </div>

            {{-- Image --}}
            <div class="relative rounded-3xl overflow-hidden shadow-2xl mb-12 bg-white flex justify-center items-center py-10">
                <img src="{{ asset('img/LOGO_1.png') }}"
                    class="w-full max-w-2xl h-auto object-contain" alt="Logo de conservación">
            </div>
        </div>

        {{-- Historia --}}
        <div class="max-w-4xl mx-auto mb-16">

            <div class="flex items-center gap-3 mb-6">

                {{-- Icon --}}
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>

                <h2 class="text-gray-900 text-3xl">
                    Nuestra Historia
                </h2>

            </div>

            <p class="text-gray-600 text-lg leading-relaxed mb-8">
                EL Consejo Ciudadano toma protesta el día 26 de Octubre 2023 en el interior del Ayuntamiento de
                Chignahuapan, conformado por 07 propietarios y 07 suplentes. Como presidenta la Mtra. Deogracias Ortega
                Ramírez y Lic. Maricela Romano Galindo como secretaria.
            </p>

            {{-- Timeline 2023-2024 --}}
            <div class="relative" x-data>

                {{-- Línea vertical --}}
                <div class="absolute left-5 top-0 bottom-0 w-0.5 bg-gradient-to-b from-blue-400 via-blue-300 to-transparent"></div>

                <div class="space-y-5">

                    @foreach ($timeline as $index => $item)
                        @php $visible = $index < $initialCount || $showAll; @endphp

                        <div
                            class="relative pl-14 transition-all duration-500 {{ $visible ? 'opacity-100 translate-y-0' : 'hidden' }}"
                        >
                            {{-- Círculo con ícono --}}
                            <div
                                class="absolute left-0 w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-full flex items-center justify-center text-lg shadow-md ring-4 ring-white">
                                {{ $item['icon'] }}
                            </div>

                            {{-- Tarjeta --}}
                            <div class="bg-white border border-blue-50 p-4 rounded-xl shadow-sm hover:shadow-md transition-shadow duration-200">

                                {{-- Etiqueta de categoría --}}
                                <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-2.5 py-0.5 rounded-full mb-2">
                                    {{ $item['label'] }}
                                </span>

                                <p class="text-gray-700 text-sm leading-relaxed">
                                    {{ $item['event'] }}
                                </p>

                            </div>
                        </div>
                    @endforeach

                </div>

                {{-- Botón Ver todas / Ver menos --}}
                @if (count($timeline) > $initialCount)
                    <div class="mt-8 flex justify-center">
                        <button
                            wire:click="toggleShowAll"
                            class="group inline-flex items-center gap-2 px-6 py-3 rounded-full font-semibold text-sm
                                   bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-200
                                   hover:from-blue-700 hover:to-blue-600 hover:shadow-blue-300
                                   active:scale-95 transition-all duration-200 cursor-pointer"
                        >
                            @if ($showAll)
                                {{-- Ícono chevron arriba --}}
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform duration-300 rotate-180 group-hover:-translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Ver menos
                            @else
                                {{-- Ícono chevron abajo --}}
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 transition-transform duration-300 group-hover:translate-y-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Ver todas las actividades
                                <span class="ml-1 bg-white/25 text-white text-xs rounded-full px-2 py-0.5">
                                    {{ count($timeline) }}
                                </span>
                            @endif
                        </button>
                    </div>
                @endif

            </div>

        </div>

        {{-- Mission / Vision --}}
        <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">

            {{-- Mission --}}
            <div
                class="bg-gradient-to-br from-blue-600 to-blue-700 rounded-3xl p-8 text-white shadow-2xl hover:-translate-y-1 transition-all">

                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                    🤝
                </div>

                <h3 class="text-2xl mb-4">
                    Nuestra Misión
                </h3>

                <p class="text-blue-100 leading-relaxed">
                    Proteger, restaurar y conservar el ecosistema de la laguna mediante
                    acciones concretas, educación ambiental y participación comunitaria
                    activa, garantizando su preservación para las generaciones futuras.
                </p>

            </div>

            {{-- Vision --}}
            <div
                class="bg-gradient-to-br from-green-600 to-green-700 rounded-3xl p-8 text-white shadow-2xl hover:-translate-y-1 transition-all">

                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                    👁️
                </div>

                <h3 class="text-2xl mb-4">
                    Nuestra Visión
                </h3>

                <p class="text-green-100 leading-relaxed">
                    Ser referentes en conservación ambiental a nivel nacional, logrando
                    la recuperación total de la laguna y estableciendo un modelo replicable
                    de gestión sostenible que inspire a otras comunidades.
                </p>

            </div>

        </div>

    </div>
</section>
