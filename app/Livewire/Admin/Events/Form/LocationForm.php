<?php

namespace App\Livewire\Admin\Events\Form;

use Livewire\Form;

/**
 * Modalidad y ubicación del evento: presencial (dirección de texto libre),
 * virtual (enlace de reunión) o híbrido (ambos).
 *
 * UBICACIÓN COMO TEXTO LIBRE: 'location' es el único dato de ubicación que
 * captura el administrador — sin coordenadas ni mapa interactivo en el
 * formulario. Ese mismo texto se usa tal cual en la vista pública para
 * construir el iframe de Google Maps (ver event-detail-page.blade.php),
 * por lo que no se hace ninguna llamada saliente a un servicio de
 * geocodificación de terceros.
 */
class LocationForm extends Form
{
    public string $modality = '';
    public string $location = '';
    public string $virtualLink = '';

    /**
     * En borrador se permite elegir la modalidad antes de completar los
     * detalles de dirección/enlace — el admin puede guardar el avance.
     */
    public function rulesArchivos(): array
    {
        return [
            'modality'    => 'required|in:presencial,virtual,hibrido',
            'location'    => 'nullable|string|max:300',
            'virtualLink' => 'nullable|url|max:500',
        ];
    }

    /**
     * Al publicar, la dirección es obligatoria si la modalidad incluye
     * presencia física, y el enlace virtual obligatorio si incluye
     * componente virtual — evaluado server-side, nunca solo en el cliente.
     */
    public function rulesPublicacion(): array
    {
        $reglas = $this->rulesArchivos();

        $reglas['location']    = 'required_if:modality,presencial,hibrido|nullable|string|max:300';
        $reglas['virtualLink'] = 'required_if:modality,virtual,hibrido|nullable|url|max:500';

        return $reglas;
    }

    /**
     * Mensajes en español para el modal de campos faltantes al publicar
     * (ver EventForm::validarParaPublicar()) — mismo motivo que
     * GeneralInfoForm::messages(): no existe lang/es/validation.php, así
     * que sin esto Livewire usa los mensajes en inglés por defecto.
     */
    public function messages(): array
    {
        return [
            'modality.required'       => 'Falta seleccionar la modalidad del evento.',
            'modality.in'              => 'La modalidad seleccionada no es válida.',
            'location.required_if'    => 'Falta la dirección o lugar del evento presencial.',
            'location.max'            => 'La dirección no puede superar los 300 caracteres.',
            'virtualLink.required_if' => 'Falta el enlace de la sala virtual.',
            'virtualLink.url'         => 'El enlace virtual no es una URL válida.',
            'virtualLink.max'         => 'El enlace virtual no puede superar los 500 caracteres.',
        ];
    }
}
