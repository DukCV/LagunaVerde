<?php

namespace App\Livewire\Admin\Widgets;

use Livewire\Component;

/**
 * Widget de mensajes recientes (interfaz estática).
 *
 * NOTA: Este widget muestra datos de demostración mientras el módulo
 * de mensajería interna no esté implementado en la base de datos.
 * Cuando se conecte a BD, los datos mock se reemplazarán con una
 * consulta a la tabla 'messages' (o equivalente).
 */
class MessagesWidget extends Component
{
    // ════════════════════════════════════════════════════════════════════
    //  RENDER
    // ════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.admin.widgets.messages-widget', [
            'mensajes' => $this->mensajesDemo(),
        ]);
    }

    // ════════════════════════════════════════════════════════════════════
    //  DATOS DE DEMOSTRACIÓN
    //  TODO: Reemplazar con consulta a BD cuando el módulo esté listo
    // ════════════════════════════════════════════════════════════════════

    /** Mensajes de demostración para la interfaz del widget */
    private function mensajesDemo(): array
    {
        return [
            [
                'id'      => 1,
                'nombre'  => 'María González',
                'avatar'  => null, // Usar iniciales si no hay avatar
                'mensaje' => '¿Cuándo podemos coordinar la próxima limpieza?',
                'tiempo'  => 'hace 5 min',
                'leido'   => false,
            ],
            [
                'id'      => 2,
                'nombre'  => 'Carlos Ramírez',
                'avatar'  => null,
                'mensaje' => 'Envío los resultados del último estudio ambiental',
                'tiempo'  => 'hace 1 hora',
                'leido'   => false,
            ],
            [
                'id'      => 3,
                'nombre'  => 'Ana Martínez',
                'avatar'  => null,
                'mensaje' => 'Gracias por el apoyo en el evento del sábado',
                'tiempo'  => 'hace 3 horas',
                'leido'   => true,
            ],
            [
                'id'      => 4,
                'nombre'  => 'Luis Fernández',
                'avatar'  => null,
                'mensaje' => 'Necesito información sobre las donaciones del mes',
                'tiempo'  => 'hace 1 día',
                'leido'   => true,
            ],
        ];
    }
}
