{{--
    livewire/home/collaborators-section.blade.php
    ─────────────────────────────────────────────────────────────────────
    Sección "Nuestros Colaboradores" — usada en el home y en "Quiénes
    Somos" (mismo componente <livewire:home.collaborators-section />).
    Grilla fija de máximo 5 tarjetas pequeñas — ver <x-collaborators.mini-card>.

    Recibe:
      $collaborators → array[] (cada elemento es un PartnerCardDto
                        serializado vía toLivewire())

    SEGURIDAD:
    • {{ }} en toda salida → escape XSS automático de Blade.
    • PartnerCardDto::fromLivewire() reconstruye el DTO inmutable antes de
      pasarlo a <x-collaborators.mini-card> — ningún dato cruza sin pasar
      por las mismas reglas de saneamiento que el listado público.
    ─────────────────────────────────────────────────────────────────────
--}}

{{-- Sin colaboradores activos: la sección no se renderiza --}}
@if (! empty($collaborators))
    <section class="py-20 bg-gradient-to-b from-gray-50 to-white">
        <div class="container mx-auto px-4">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-12">
                <div>
                    <h2 class="text-4xl text-gray-900 mb-2">
                        Nuestros Colaboradores
                    </h2>
                    <p class="text-lg text-gray-600">
                        Gracias a quienes hacen posible nuestra misión
                    </p>
                </div>

                <x-buttons.view-all :href="route('collaborators')" wire:navigate>
                    Ver todos los colaboradores
                </x-buttons.view-all>
            </div>

            {{-- Grilla fija — máximo 5 tarjetas (ver CollaboratorsSection::MAX_COLLABORATORS) --}}
            <div class="flex flex-wrap justify-center gap-6">
                @foreach ($collaborators as $collaborator)
                    @php
                        // Reconstruye el DTO inmutable desde su forma serializada (Wireable).
                        $partner = \App\DTOs\PartnerCardDto::fromLivewire($collaborator);
                    @endphp
                    <x-collaborators.mini-card :partner="$partner" wire:key="collab-{{ $partner->id }}" />
                @endforeach
            </div>

        </div>
    </section>
@endif
