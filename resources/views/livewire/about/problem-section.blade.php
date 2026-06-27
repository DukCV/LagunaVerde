<section id="problema" class="py-20 bg-gradient-to-b from-gray-100 to-gray-200">

    <div class="container mx-auto px-4">

        {{-- HEADER --}}
        <div class="text-center max-w-3xl mx-auto mb-16">

            <div class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-full mb-6">

                ⚠️ <span>El Contexto que Enfrentamos</span>

            </div>

            <h2 class="text-gray-900 text-4xl lg:text-5xl mb-6">
                La Problemática Actual
            </h2>

            <p class="text-gray-600 text-lg leading-relaxed">
                Durante décadas, la laguna ha sufrido un deterioro constante debido a la
                actividad humana descontrolada. Estos son los principales desafíos que
                enfrentamos hoy.
            </p>

        </div>

        {{-- STATS --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto mb-16">

            @foreach ($stats as $stat)
                <div class="bg-white rounded-2xl p-6 shadow-lg hover:shadow-xl transition-all">

                    <div class="mb-4 text-3xl">

                        @if ($stat['icon'] === 'droplet')
                            💧
                        @elseif($stat['icon'] === 'trend')
                            📉
                        @elseif($stat['icon'] === 'factory')
                            🏭
                        @elseif($stat['icon'] === 'trash')
                            🗑️
                        @endif

                    </div>

                    <div class="text-4xl text-gray-900 mb-2">
                        {{ $stat['value'] }}
                    </div>

                    <div class="text-gray-900 mb-2">
                        {{ $stat['label'] }}
                    </div>

                    <p class="text-sm text-gray-600 leading-relaxed">
                        {{ $stat['description'] }}
                    </p>

                </div>
            @endforeach

        </div>

        {{-- VISUAL COMPARISON --}}
        <div class="max-w-6xl mx-auto mb-16">

            <div class="grid md:grid-cols-2 gap-8">

                {{-- POLLUTED --}}
                <div class="relative rounded-2xl overflow-hidden shadow-2xl">

                    <img src="https://fotos.e-consulta.com/faena_municipal_mata_a_los_peces_en_la_laguna_de_chignahuapan_1.jpeg"
                        class="w-full h-80 object-cover" alt="Laguna contaminada" />

                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                    <div class="absolute bottom-6 left-6 right-6">

                        <div class="inline-block px-4 py-2 bg-red-500 text-white rounded-lg mb-2">
                            Estado Actual
                        </div>

                        <p class="text-white text-lg">
                            Contaminación visible y pérdida de biodiversidad
                        </p>

                    </div>

                </div>

                {{-- CLEAN --}}
                <div class="relative rounded-2xl overflow-hidden shadow-2xl">

                    <img src="https://www.urbanopuebla.com.mx/wp-content/uploads/2023/10/Chignahuapan.jpg"
                        class="w-full h-80 object-cover" alt="Laguna limpia" />

                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                    <div class="absolute bottom-6 left-6 right-6">

                        <div class="inline-block px-4 py-2 bg-green-500 text-white rounded-lg mb-2">
                            Nuestro Objetivo
                        </div>

                        <p class="text-white text-lg">
                            Ecosistema saludable y biodiversidad restaurada
                        </p>

                    </div>

                </div>

            </div>

        </div>

        {{-- FACTS --}}
        <div class="max-w-4xl mx-auto">

            <h3 class="text-gray-900 text-2xl mb-6 text-center">
                Datos Críticos
            </h3>

            <div class="space-y-4">

                @foreach ($facts as $fact)
                    <div class="bg-white border-l-4 border-red-500 rounded-lg p-6 shadow-md">

                        <h4 class="text-gray-900 text-lg mb-2">
                            {{ $fact['title'] }}
                        </h4>

                        <p class="text-gray-600 leading-relaxed">
                            {{ $fact['text'] }}
                        </p>

                    </div>
                @endforeach

            </div>

        </div>

    </div>

</section>
