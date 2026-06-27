<?php

// ════════════════════════════════════════════════════════════════════════════
//  SEEDER: Asistencias de demostración (event_registrations)
//
//  NO crea una tabla nueva: usa la YA EXISTENTE 'event_registrations' (ver
//  migración 2026_04_03_023331_create_event_registrations_table). Esa tabla
//  ya cubre lo que pedía un pivote "event_user" — y más: status con
//  historial (registered/waitlist/cancelled/attended/no_show), registered_at,
//  índices y unique(event_id,user_id).
//
//  Registra a 2 usuarios de UserSeeder en 2 eventos de EventSeeder para
//  poder probar de inmediato el estado "Cancelar Asistencia" del nuevo
//  módulo (ver App\Livewire\Events\EventDetail\EventAttendance).
// ════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventRegistrationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🎫 Sembrando asistencias de demostración...');

        $this->registrar('usuario@lagunverde.mx', 'Taller de Monitoreo de Calidad del Agua');
        $this->registrar('colaborador@lagunaverde.mx', 'Jornada de Limpieza y Reforestación Comunitaria');

        $this->command->info('  ✅ Asistencias sembradas correctamente.');
    }

    /** Idempotente: updateOrCreate respeta el unique(event_id,user_id). */
    private function registrar(string $email, string $eventName): void
    {
        $user  = User::where('email', $email)->first();
        $event = Event::where('name', $eventName)->first();

        if ($user === null || $event === null) {
            $this->command->warn("  ⚠ Omitido: usuario o evento no encontrado ({$email} / {$eventName})");

            return;
        }

        EventRegistration::updateOrCreate(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['status' => 'registered', 'registered_at' => now()],
        );

        $this->command->line("  ✓ {$user->name} → {$event->name}");
    }
}
