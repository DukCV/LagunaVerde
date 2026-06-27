<?php

namespace App\Livewire\Contact;

use Livewire\Component;

class ContactForm extends Component
{
    public $name = '';
    public $email = '';
    public $subject = '';
    public $message = '';
    public $acceptPrivacy = false;

    public $success = false;

    public $subjects = [
        'Consulta General',
        'Reporte Ambiental',
        'Sugerencias',
        'Alianzas y Colaboraciones',
        'Medios de Comunicación',
        'Otro',
    ];

    protected function rules()
    {
        return [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'subject' => 'required',
            'message' => 'required|min:10',
            'acceptPrivacy' => 'accepted'
        ];
    }

    protected $messages = [
        'name.required' => 'El nombre es obligatorio',
        'email.required' => 'El correo es obligatorio',
        'email.email' => 'Correo inválido',
        'subject.required' => 'Selecciona un asunto',
        'message.required' => 'El mensaje es obligatorio',
        'message.min' => 'Mínimo 10 caracteres',
        'acceptPrivacy.accepted' => 'Debes aceptar el aviso de privacidad'
    ];

    public function submit()
    {
        $this->validate();

        // Simulación (sin migración)
        sleep(1);

        $this->success = true;

        $this->reset([
            'name',
            'email',
            'subject',
            'message',
            'acceptPrivacy'
        ]);
    }

    public function render()
    {
        return view('livewire.contact.contact-form');
    }
}
