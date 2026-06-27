{{--
    components/collaborators/card.blade.php
    ─────────────────────────────────────────────────────────────────────
    Tarjeta de socio colaborador. ÚNICA fuente de verdad para este diseño —
    compartida entre el listado público /colaboradores y la sección
    "Nuestros Colaboradores" del home/Quiénes Somos (DRY).

    Props:
      - partner (App\DTOs\PartnerCardDto, obligatorio)
      - showDetailsButton (bool, opcional, default true): controla el CTA
        "Ver detalles" (abre el modal). El home/Quiénes Somos lo desactivan
        con :show-details-button="false" — esas vistas no tienen el modal
        de detalles (vive sólo en CollaboratorsIndex), y muestran un botón
        propio ("Ver todos los colaboradores") para llevar al listado completo.

    SEGURIDAD EN VISTA:
    • {{ }} en toda salida → escape XSS automático de Blade.
    • e() en el atributo src del logo → previene XSS en la URL de la imagen.
    • Las redes sociales se delegan a <x-social-links>, que revalida el
      esquema http(s) de cada URL antes de renderizarla en un href (defensa
      en profundidad, independiente de la validación ya aplicada al guardar).
    • $partner->id solo se usa como wire:key y para abrir el modal de
      detalles (búsqueda en memoria, sin consulta a BD). Partner no tiene
      página de detalle público, por lo que no hay enumeración de recursos
      posible a través de este identificador.
    ─────────────────────────────────────────────────────────────────────
--}}

@props(['partner', 'showDetailsButton' => true])

<article class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-xl transition-shadow group flex flex-col">

    {{-- ── Logo / Imagen ────────────────────────────────────────────── --}}
    <div class="relative aspect-video bg-gray-100 shrink-0">
        @if ($partner->logoUrl)
            <img
                src="{{ e($partner->logoUrl) }}"
                alt="Logo de {{ $partner->name }}"
                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                loading="lazy"
                width="640"
                height="360"
            >
        @else
            {{-- Placeholder sin logo registrado --}}
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-50 to-green-50">
                <span class="text-5xl opacity-30" aria-hidden="true">🤝</span>
            </div>
        @endif

        {{-- Categoría --}}
        <div class="absolute top-4 left-4">
            @php
                $categoryConfig = match($partner->type) {
                    'Corporativo'   => ['icon' => '🏢', 'color' => 'bg-blue-100 text-blue-800 border border-blue-200 shadow-sm'],
                    'Educativo'     => ['icon' => '🎓', 'color' => 'bg-purple-100 text-purple-800 border border-purple-200 shadow-sm'],
                    'ONG'           => ['icon' => '🌍', 'color' => 'bg-green-100 text-green-800 border border-green-200 shadow-sm'],
                    'Gubernamental' => ['icon' => '🏛️', 'color' => 'bg-gray-100 text-gray-800 border border-gray-200 shadow-sm'],
                    'Tecnológico'   => ['icon' => '💻', 'color' => 'bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-sm'],
                    'Fundación'     => ['icon' => '🤝', 'color' => 'bg-teal-100 text-teal-800 border border-teal-200 shadow-sm'],
                    'Comunitario'   => ['icon' => '👥', 'color' => 'bg-orange-100 text-orange-800 border border-orange-200 shadow-sm'],
                    'Persona'       => ['icon' => '👤', 'color' => 'bg-pink-100 text-pink-800 border border-pink-200 shadow-sm'],
                    default         => ['icon' => '🔖', 'color' => 'bg-white/90 text-gray-900 border border-gray-200 shadow-sm backdrop-blur-sm'],
                };
            @endphp
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold tracking-wide {{ $categoryConfig['color'] }}">
                <span aria-hidden="true" class="text-sm">{{ $categoryConfig['icon'] }}</span>
                {{ $partner->type }}
            </span>
        </div>
    </div>

    {{-- ── Contenido ────────────────────────────────────────────────── --}}
    <div class="p-6 space-y-4 flex flex-col grow">

        <h3 class="text-xl text-gray-900">
            {{ $partner->name }}
        </h3>

        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                Quiénes son
            </p>
            <p class="text-sm text-gray-600 line-clamp-3">
                {{ $partner->whoTheyAre }}
            </p>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">
                Cómo ayudan
            </p>
            <p class="text-sm text-gray-600 line-clamp-3">
                {{ $partner->howTheySupport }}
            </p>
        </div>

        <div class="mt-auto space-y-4">

            {{-- Redes sociales — mismo componente que la sección de colaboradores del home --}}
            <x-social-links :links="[
                'website'   => $partner->website,
                'instagram' => $partner->instagram,
                'facebook'  => $partner->facebook,
                'twitter'   => $partner->twitter,
                'linkedin'  => $partner->linkedin,
                'youtube'   => $partner->youtube,
            ]" />

            {{-- Fechas de registro y última actualización --}}
            <div class="flex items-center justify-between gap-2 pt-4 border-t border-gray-200 text-xs text-gray-500">
                <span>Registrado: {{ $partner->createdAt }}</span>
                <span>Actualizado: {{ $partner->updatedAt }}</span>
            </div>

            {{-- CTA — abre el modal de detalles (sin consulta adicional a BD) --}}
            @if ($showDetailsButton)
                <button
                    type="button"
                    wire:click="openDetails({{ $partner->id }})"
                    class="flex items-center justify-center gap-2 w-full py-3 px-4 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-all"
                >
                    Ver detalles
                </button>
            @endif

        </div>
    </div>
</article>
