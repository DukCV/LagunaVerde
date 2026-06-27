<?php

namespace App\Livewire\Admin\Events\Form;

use Livewire\Form;

/**
 * Datos generales del evento: título, descripción breve, cuerpo (Trix) y categoría.
 *
 * DOS NIVELES DE VALIDACIÓN (mismo patrón que NewsForm):
 *  - rulesArchivos(): nivel permisivo usado al guardar un borrador.
 *  - rulesPublicacion(): nivel estricto usado al publicar, extiende al anterior.
 */
class GeneralInfoForm extends Form
{
    public string $name = '';
    public string $description = '';
    public string $content = '';
    public string $categoryId = '';

    /**
     * Reglas mínimas de seguridad/formato — permiten guardar un borrador
     * incompleto. 'categoryId' es obligatorio incluso en borrador: a
     * diferencia de news.category_id, la columna events.category_id NO
     * admite NULL (no se modificó esa restricción para no arriesgar un
     * ALTER en producción sólo por flexibilidad de borradores).
     */
    public function rulesArchivos(): array
    {
        return [
            'name'        => 'nullable|string|max:180',
            'description' => 'nullable|string|max:500',
            'content'     => 'nullable|string',
            'categoryId'  => 'required|integer|exists:categories,id',
        ];
    }

    /**
     * Reglas completas para publicar: parte de rulesArchivos() (DRY) y
     * sobrescribe los campos que pasan a ser obligatorios.
     */
    public function rulesPublicacion(): array
    {
        $reglas = $this->rulesArchivos();

        $reglas['name']        = 'required|string|max:180';
        $reglas['description'] = 'required|string|max:500';

        // El editor Trix genera HTML: strip_tags extrae el texto visible
        // real para medir su longitud mínima (igual que NewsForm).
        $reglas['content'] = ['required', function (string $atributo, mixed $valor, \Closure $fail): void {
            if (mb_strlen(trim(strip_tags((string) $valor))) < 10) {
                $fail('El cuerpo del evento es muy corto (mínimo 10 caracteres visibles).');
            }
        }];

        return $reglas;
    }

    /**
     * Mensajes en español para el modal de campos faltantes al publicar
     * (ver EventForm::validarParaPublicar()) — sin esto, Livewire usa los
     * mensajes en inglés por defecto de Laravel porque no existe un archivo
     * lang/es/validation.php en el proyecto. Mismo patrón que
     * NewsForm::mensajesValidacionPublicacion().
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Falta el nombre del evento.',
            'name.max'              => 'El nombre no puede superar los 180 caracteres.',
            'description.required' => 'Falta la descripción breve del evento.',
            'description.max'      => 'La descripción no puede superar los 500 caracteres.',
            'content.required'     => 'Falta el cuerpo del evento.',
            'categoryId.required'  => 'Falta seleccionar una categoría.',
            'categoryId.integer'   => 'La categoría seleccionada no es válida.',
            'categoryId.exists'    => 'La categoría seleccionada no es válida.',
        ];
    }
}
