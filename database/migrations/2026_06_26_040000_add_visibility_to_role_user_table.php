<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Agrega el interruptor de visibilidad pública a role_user
//
//  Propósito:
//    "Mi Perfil" (panel de administración) permite que un Administrador
//    decida si su ficha (foto, puesto, semblanza, redes sociales — ya
//    presentes en este mismo pivote, ver add_position_and_permissions_to_
//    role_user_table y add_public_profile_to_role_user_table) aparece en la
//    sección pública "Quiénes Somos" → "Nuestro Equipo" (App\Livewire\About\
//    TeamSection). Igual criterio que esas columnas: pertenece a la
//    asignación de rol concreta, no al usuario en general.
//
//  Valor por defecto: false (privado por defecto) — un administrador nuevo
//    o recién promovido NO aparece públicamente hasta que él mismo decide
//    mostrarse desde "Mi Perfil". Evita exponer datos personales sin
//    consentimiento explícito.
//
//  Compatibilidad:
//    Migración puramente aditiva (ALTER TABLE ADD COLUMN, con default) — no
//    afecta ninguna fila existente en role_user más que fijar este nuevo
//    campo en su valor por defecto.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->boolean('show_in_about_us')->default(false)->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn('show_in_about_us');
        });
    }
};
