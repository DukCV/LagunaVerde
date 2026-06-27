{{--
    Sección "Nuestro Equipo" — Quiénes Somos (pública).
    Componente: App\Livewire\About\TeamSection

    Datos: $equipo es un array de TeamMemberDto serializados vía
    toLivewire() (Administradores activos, ver TeamSection::mount()).

    SEGURIDAD:
     - Toda salida usa {{ }} → escape XSS automático de Blade.
     - En la TARJETA, las redes sociales se renderizan con <x-social-links>
       (servidor/Blade), que revalida el esquema http(s) de cada URL antes
       de exponerla en un href (defensa en profundidad — TeamMemberDto ya
       las revalidó al leer la BD). En el MODAL los datos vienen de Alpine
       (@js($equipo)), así que se usan enlaces <a> explícitos por
       plataforma con :href — <x-social-icon> no puede elegir su ícono según
       estado de Alpine porque se resuelve en el servidor, no en el cliente.
     - La foto de perfil viene únicamente de User::profilePhotoUrl(); nunca
       de una ruta enviada por el cliente.

    RESPONSIVE:
     - Grid de 1 columna en móvil, 2 en tablet, 4 en escritorio — sin
       desplazamientos de layout al cargar (alturas de tarjeta consistentes).
--}}
@if (! empty($equipo))
    <section id="equipo" class="py-20 bg-gradient-to-b from-gray-50 to-white">

        <div class="container mx-auto px-4" x-data="{ seleccionado: null, equipo: @js($equipo) }">

            {{-- ── Encabezado ──────────────────────────────────────────── --}}
            <div class="text-center max-w-3xl mx-auto mb-16">
                <div class="inline-block px-4 py-2 bg-purple-100 text-purple-700 rounded-full mb-6">
                    Las Personas Detrás del Cambio
                </div>
                <h2 class="text-gray-900 text-4xl lg:text-5xl mb-6">
                    Nuestro Equipo
                </h2>
                <p class="text-gray-600 text-lg leading-relaxed">
                    Un grupo multidisciplinario de profesionales apasionados por la conservación.
                </p>
            </div>

            {{-- ── Grid de tarjetas ────────────────────────────────────── --}}
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-8 max-w-6xl mx-auto">
                @foreach ($equipo as $miembro)
                    @php
                        // Reconstruye el DTO inmutable desde su forma serializada (Wireable).
                        $integrante = \App\DTOs\TeamMemberDto::fromLivewire($miembro);
                    @endphp
                    <div
                        class="group cursor-pointer"
                        wire:key="equipo-{{ $integrante->id }}"
                        @click="seleccionado = {{ $integrante->id }}"
                    >
                        <div class="bg-white rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all hover:-translate-y-2 h-full flex flex-col">

                            {{-- ── Foto de perfil (o iniciales) ────────────── --}}
                            <div class="relative h-80 overflow-hidden bg-gray-100 shrink-0">
                                @if ($integrante->photoUrl)
                                    <img
                                        src="{{ e($integrante->photoUrl) }}"
                                        alt="Foto de {{ $integrante->name }}"
                                        loading="lazy"
                                        decoding="async"
                                        class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                                    >
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-500 to-indigo-600">
                                        <span class="text-white text-5xl font-semibold" aria-hidden="true">{{ $integrante->initials }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- ── Información ─────────────────────────────── --}}
                            <div class="p-6 flex flex-col grow">
                                <h3 class="text-gray-900 text-xl mb-1">
                                    {{ $integrante->name }}
                                </h3>
                                <p class="text-blue-600 mb-4">
                                    {{ $integrante->position ?? 'Administrador' }}
                                </p>

                                @if ($integrante->publicBio !== '')
                                    <p class="text-sm text-gray-600 line-clamp-3 mb-4">
                                        {{ $integrante->publicBio }}
                                    </p>
                                @endif

                                <div class="mt-auto space-y-3">
                                    <x-social-links :links="[
                                        'website'   => $integrante->website,
                                        'instagram' => $integrante->instagram,
                                        'facebook'  => $integrante->facebook,
                                        'twitter'   => $integrante->twitter,
                                        'linkedin'  => $integrante->linkedin,
                                        'youtube'   => $integrante->youtube,
                                    ]" />

                                    <button
                                        type="button"
                                        class="text-sm text-gray-600 hover:text-blue-600 text-left"
                                        aria-label="Ver perfil completo de {{ $integrante->name }}"
                                    >
                                        Ver perfil completo →
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ── Modal de perfil completo ────────────────────────────── --}}
            <div
                x-show="seleccionado !== null"
                x-transition
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="display: none;"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="seleccionado = null"></div>

                <template x-for="miembro in equipo" :key="miembro.id">
                    <div
                        x-show="seleccionado === miembro.id"
                        class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
                    >
                        <button
                            type="button"
                            @click="seleccionado = null"
                            class="absolute top-4 right-4 z-10 p-2 bg-gray-100 hover:bg-gray-200 rounded-full"
                            aria-label="Cerrar"
                        >
                            <x-admin-icon name="x-mark" class="w-5 h-5 text-gray-700" />
                        </button>

                        {{-- Foto / iniciales --}}
                        <div class="relative h-80 bg-gray-100">
                            <template x-if="miembro.photoUrl">
                                <img :src="miembro.photoUrl" :alt="'Foto de ' + miembro.name" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!miembro.photoUrl">
                                <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-500 to-indigo-600">
                                    <span class="text-white text-6xl font-semibold" x-text="miembro.initials"></span>
                                </div>
                            </template>

                            <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>

                            <div class="absolute bottom-6 left-6 right-6 text-white">
                                <h3 class="text-3xl mb-2" x-text="miembro.name"></h3>
                                <p class="text-blue-300 text-lg" x-text="miembro.position || 'Administrador'"></p>
                            </div>
                        </div>

                        {{-- Semblanza + redes sociales --}}
                        <div class="p-8">
                            <p class="text-gray-600 leading-relaxed mb-6" x-text="miembro.publicBio" x-show="miembro.publicBio"></p>

                            {{--
                                <x-social-icon> es un componente Blade — se resuelve en el
                                SERVIDOR, no puede elegir su "name" según el estado de Alpine
                                en el cliente. Por eso aquí van 6 enlaces explícitos (uno por
                                plataforma, cada uno con su propio ícono ya fijo) en vez de un
                                x-for que intente variar el ícono dinámicamente.
                            --}}
                            <div
                                class="flex items-center gap-2 flex-wrap pt-6 border-t border-gray-200"
                                x-show="miembro.website || miembro.instagram || miembro.facebook || miembro.twitter || miembro.linkedin || miembro.youtube"
                            >
                                <a x-show="miembro.website" :href="miembro.website" target="_blank" rel="noopener noreferrer" title="Sitio web" aria-label="Sitio web" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="website" class="w-4 h-4" />
                                </a>
                                <a x-show="miembro.instagram" :href="miembro.instagram" target="_blank" rel="noopener noreferrer" title="Instagram" aria-label="Instagram" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="instagram" class="w-4 h-4" />
                                </a>
                                <a x-show="miembro.facebook" :href="miembro.facebook" target="_blank" rel="noopener noreferrer" title="Facebook" aria-label="Facebook" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="facebook" class="w-4 h-4" />
                                </a>
                                <a x-show="miembro.twitter" :href="miembro.twitter" target="_blank" rel="noopener noreferrer" title="Twitter / X" aria-label="Twitter / X" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="twitter" class="w-4 h-4" />
                                </a>
                                <a x-show="miembro.linkedin" :href="miembro.linkedin" target="_blank" rel="noopener noreferrer" title="LinkedIn" aria-label="LinkedIn" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="linkedin" class="w-4 h-4" />
                                </a>
                                <a x-show="miembro.youtube" :href="miembro.youtube" target="_blank" rel="noopener noreferrer" title="YouTube" aria-label="YouTube" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-600 hover:bg-blue-600 hover:text-white transition-colors">
                                    <x-social-icon name="youtube" class="w-4 h-4" />
                                </a>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

        </div>
    </section>
@endif
