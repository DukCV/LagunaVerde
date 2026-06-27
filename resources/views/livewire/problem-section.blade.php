<section class="py-20 bg-gray-50">
    <div class="container mx-auto px-4">

        {{-- Header --}}
        <div class="text-center max-w-3xl mx-auto mb-16">
            <div class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 text-red-700 rounded-full mb-4">
                ⚠️
                <span class="text-sm">Situación Actual</span>
            </div>

            <h2 class="text-gray-900 text-4xl lg:text-5xl mb-4">
                Tu Laguna Necesita Tu Ayuda
            </h2>

            <p class="text-gray-600 text-lg">
                La situación actual de nuestra laguna es crítica. Estos son los principales desafíos
                que enfrentamos y que necesitamos resolver urgentemente.
            </p>
        </div>

        {{-- Cards --}}
        <div class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            @foreach($problems as $problem)
                <div
                    class="bg-white rounded-2xl p-8 shadow-lg hover:shadow-2xl
                           transition-all hover:-translate-y-1"
                >
                    {{-- Icon --}}
                    <div class="mb-6 text-3xl {{ $problem['color'] }}">
                        @switch($problem['icon'])
                            @case('droplet') 💧 @break
                            @case('fish') 🐟 @break
                            @case('tree') 🌲 @break
                        @endswitch
                    </div>

                    {{-- Title --}}
                    <h3 class="text-gray-900 text-2xl mb-3">
                        {{ $problem['title'] }}
                    </h3>

                    {{-- Stat --}}
                    <div class="text-5xl text-gray-900 mb-4">
                        {{ $problem['stat'] }}
                    </div>

                    {{-- Description --}}
                    <p class="text-gray-600 leading-relaxed">
                        {{ $problem['description'] }}
                    </p>

                    {{-- Severity --}}
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center gap-2">
                            <div
                                class="w-3 h-3 rounded-full
                                {{
                                    $problem['severity'] === 'critical'
                                        ? 'bg-red-500'
                                        : ($problem['severity'] === 'high'
                                            ? 'bg-orange-500'
                                            : 'bg-yellow-500')
                                }}"
                            ></div>

                            <span class="text-sm text-gray-500">
                                {{
                                    $problem['severity'] === 'critical'
                                        ? 'Prioridad Crítica'
                                        : ($problem['severity'] === 'high'
                                            ? 'Prioridad Alta'
                                            : 'Prioridad Media')
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</section>
