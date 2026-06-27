<footer class="bg-gray-900 text-white pt-16 pb-8">
    <div class="container mx-auto px-4">

        {{-- Main Footer --}}
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">

            {{-- About --}}
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <img src="{{ asset('img/LOGO_1.png') }}" alt="Logo Consejo Ciudadano" class="w-12 h-12 object-contain">
                    </div>

                    <div>
                        <div class="text-lg leading-tight">Consejo ciudadano</div>
                        <div class="text-xs text-gray-400 leading-tight">Cuidado, Conservación y Protección de la Laguna</div>
                    </div>
                </div>

                <p class="text-gray-400 leading-relaxed mb-4">
                    Organización dedicada a la protección y restauración de la laguna
                    para las generaciones presentes y futuras.
                </p>

                <div class="flex gap-3">
                    @foreach ($socialLinks as $social)
                        <a href="{{ $social['href'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="{{ $social['label'] }}"
                            class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center hover:bg-blue-600 transition-colors">
                            <x-social-icon name="{{ strtolower($social['label']) }}" class="w-5 h-5 text-white" />
                        </a>
                    @endforeach
                </div>
            </div>


            {{-- Navegación --}}
            <div>
                <h3 class="text-lg mb-4">Navegación</h3>

                <ul class="space-y-2">
                    @foreach ($quickLinks as $link)
                        <li>
                            <button wire:click="navigate('{{ $link['page'] }}')"
                                class="text-gray-400 hover:text-white transition-colors">
                                {{ $link['label'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>


            {{-- Legal --}}
            <div>
                <h3 class="text-lg mb-4">Legal</h3>

                <ul class="space-y-2">
                    @foreach ($legalLinks as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="text-gray-400 hover:text-white transition-colors">
                                {{ $link['label'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>


            {{-- Contacto --}}
            <div>

                <h3 class="text-lg mb-4">Contacto</h3>

                <ul class="space-y-3 text-gray-400">

                    <li>
                        <a href="mailto:consejociudadanoccplc@gmail.com" class="hover:text-white">
                            consejociudadanoccplc@gmail.com
                        </a>
                    </li>

                </ul>

            </div>

        </div>


        {{-- Newsletter --}}
        <div class="border-t border-gray-800 pt-8 mb-8">

            <div class="max-w-2xl mx-auto text-center">

                <h3 class="text-xl mb-2">Mantente Informado</h3>

                <p class="text-gray-400 mb-6">
                    Suscríbete a nuestro boletín y recibe actualizaciones sobre nuestros proyectos
                </p>

                <form class="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">

                    <input type="email" placeholder="Tu correo electrónico"
                        class="flex-1 px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 text-white placeholder-gray-500" />

                    <button type="submit" class="px-6 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                        Suscribirse
                    </button>

                </form>

            </div>

        </div>


        {{-- Bottom --}}
        <div
            class="border-t border-gray-800 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-400">

            <div>
                © {{ $year }} Consejo Ciudadano. Todos los derechos reservados.
            </div>

            <div class="flex items-center gap-2">
                <span>Hecho con</span>
                <span class="text-red-500">❤</span>
                <span>por el ecosistema</span>
            </div>

        </div>

    </div>
</footer>
