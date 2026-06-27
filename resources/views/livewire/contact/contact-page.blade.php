{{--
    -mt-24 cancela el pt-24 del body (principal.blade.php) para que bg-gray-50
    cubra desde y=0 y elimine la franja oscura visible bajo la cabecera fija.
--}}
<div class="min-h-screen bg-gray-50 -mt-24 pt-24 pb-16">

<div class="container mx-auto px-4">

    {{-- Header --}}
    <div class="text-center mb-12">
        <h1 class="text-4xl lg:text-5xl font-bold text-gray-900 mb-4">
            Ponte en contacto
        </h1>

        <p class="text-gray-600 max-w-2xl mx-auto">
            Estamos listos para escucharte.
        </p>
    </div>

    {{-- Form --}}
    <div class="max-w-3xl mx-auto mb-16">
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <livewire:contact.contact-form />
        </div>
    </div>

    {{-- Ubicación --}}
    <div class="grid lg:grid-cols-3 gap-8">

        {{-- Map --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
                <iframe
                    class="w-full h-100"
                    src="https://maps.google.com/maps?q=Laguna+de+Chignahuapan,+Puebla,+Mexico&output=embed">
                </iframe>
            </div>
        </div>

        {{-- Info --}}
        <div class="bg-white rounded-2xl shadow-lg p-8 space-y-6 h-fit sticky top-24">

            <div>
                <h3 class="text-xl font-semibold text-gray-900">
                    Información
                </h3>
            </div>

            <div>
                <div class="font-medium text-gray-900">Email</div>
                <a href="mailto:consejociudadanoccplc@gmail.com" class="text-blue-600 hover:text-blue-700 transition-colors">
                    consejociudadanoccplc@gmail.com
                </a>
            </div>

        </div>

    </div>

</div>
</div>
