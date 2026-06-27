<section id="proyectos" class="py-20 bg-gradient-to-b from-white to-blue-50">

    <div class="container mx-auto px-4">

        {{-- HEADER --}}
        <div class="text-center max-w-3xl mx-auto mb-16">

            <div class="inline-block px-4 py-2 bg-green-100 text-green-700 rounded-full mb-6">
                La Solución en Acción
            </div>

            <h2 class="text-gray-900 text-4xl lg:text-5xl mb-6">
                Proyectos y Logros
            </h2>

            <p class="text-gray-600 text-lg leading-relaxed">
                Nuestro trabajo está dando frutos. Estos son los resultados tangibles
                de años de esfuerzo y compromiso con la conservación.
            </p>

        </div>

        {{-- VIDEO --}}
        <div class="max-w-5xl mx-auto mb-20">

            {{-- aspect-video mantiene la proporción 16:9 sin layout shift --}}
            <div class="aspect-video w-full rounded-2xl overflow-hidden shadow-2xl">
                <iframe
                    src="https://www.youtube.com/embed/Dbe4xZvrPcE"
                    title="Video de Laguna Verde"
                    class="w-full h-full border-0"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    allowfullscreen
                ></iframe>
            </div>

        </div>

        {{-- ACHIEVEMENTS --}}
        <div class="max-w-6xl mx-auto">

            <h3 class="text-gray-900 text-3xl mb-12 text-center">
                Logros Destacados
            </h3>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">

                @foreach ($achievements as $achievement)
                    <div
                        class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all hover:-translate-y-2 group">

                        <div class="mb-6 text-3xl">

                            @if ($achievement['icon'] == 'recycle')
                                ♻️
                            @elseif($achievement['icon'] == 'tree')
                                🌳
                            @elseif($achievement['icon'] == 'water')
                                💧
                            @elseif($achievement['icon'] == 'fish')
                                🐟
                            @elseif($achievement['icon'] == 'users')
                                👥
                            @elseif($achievement['icon'] == 'award')
                                🏆
                            @endif

                        </div>

                        <div class="mb-2">

                            <span class="text-5xl text-gray-900">
                                {{ $achievement['value'] }}
                            </span>

                            <span class="text-xl text-gray-600 ml-2">
                                {{ $achievement['unit'] }}
                            </span>

                        </div>

                        <h4 class="text-gray-900 text-xl mb-3">
                            {{ $achievement['label'] }}
                        </h4>

                        <p class="text-gray-600 leading-relaxed">
                            {{ $achievement['description'] }}
                        </p>

                        <div class="mt-6 pt-6 border-t border-gray-200">

                            <div class="w-full bg-gray-200 rounded-full h-2">

                                <div class="bg-blue-600 h-2 rounded-full"
                                    style="width: {{ str_contains($achievement['value'], '%') ? $achievement['value'] : '100%' }}">
                                </div>

                            </div>

                        </div>

                    </div>
                @endforeach

            </div>

        </div>

        {{-- PROJECT HIGHLIGHTS --}}
        <div class="max-w-4xl mx-auto mt-20">

            <h3 class="text-gray-900 text-3xl mb-8 text-center">
                Proyectos Emblemáticos
            </h3>

            <div class="space-y-6">

                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-2xl p-8 shadow-xl">

                    <h4 class="text-2xl mb-3">
                        Programa de Monitoreo Continuo
                    </h4>

                    <p class="text-blue-100 leading-relaxed mb-4">
                        Sistema permanente de análisis de calidad del agua con tecnología avanzada y reportes mensuales
                        públicos.
                    </p>

                    <div class="inline-block px-4 py-2 bg-white/20 rounded-lg text-sm">
                        En curso desde 2018
                    </div>

                </div>

                <div class="bg-gradient-to-r from-green-600 to-green-700 text-white rounded-2xl p-8 shadow-xl">

                    <h4 class="text-2xl mb-3">
                        Educación Ambiental en Escuelas
                    </h4>

                    <p class="text-green-100 leading-relaxed mb-4">
                        Programa educativo que ha alcanzado a más de 50 escuelas locales formando defensores
                        ambientales.
                    </p>

                    <div class="inline-block px-4 py-2 bg-white/20 rounded-lg text-sm">
                        15,000+ estudiantes impactados
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>
