<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Vincula opcionalmente un socio (partner) con una cuenta de usuario
//
//  Propósito:
//    Cuando un administrador asigna el rol 'Colaborador' a un usuario desde
//    el panel ("Administrar rol"), ese usuario obtiene un perfil de socio
//    colaborador (mismos campos que un Partner público: logo, nombre,
//    categoría, "quién es", "cómo apoya", redes sociales). En vez de duplicar
//    todo ese esquema en una tabla nueva, se reutiliza 'partners' y se le
//    agrega una columna 'user_id' NULLABLE:
//      - user_id = null   → socio público tradicional (organización sin login).
//      - user_id = X      → el perfil de colaborador de la cuenta de usuario X.
//
//  Relación 1-a-nullable-1:
//    UNIQUE en user_id garantiza que cada usuario tenga, a lo sumo, UN
//    perfil de socio asociado.
//
//  Borrado del usuario:
//    nullOnDelete() — si la cuenta de usuario se elimina, el perfil público
//    de socio NO se borra junto con ella (podría seguir siendo relevante
//    públicamente); solo se desvincula.
//
//  Compatibilidad:
//    Migración puramente aditiva — todos los socios existentes (sin user_id)
//    siguen funcionando exactamente igual; el comportamiento del sitio
//    público de Partners Management no cambia para ellos.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->foreignId('user_id')
                  ->nullable()
                  ->unique()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
