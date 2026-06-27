<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Agrega perfil público a la tabla pivote role_user
//
//  Propósito:
//    Para la sección "Nuestro Equipo" de la página pública "Quiénes Somos",
//    cada Administrador necesita una semblanza pública y enlaces a redes
//    sociales. Igual que 'position'/'permissions' (ver migración
//    add_position_and_permissions_to_role_user_table), estos datos
//    pertenecen a la asignación de rol 'Administrador' concreta, no al
//    usuario en general — un 'Usuario Normal' no tiene "semblanza pública"
//    porque nunca se muestra en esa sección.
//
//    - public_bio    : TEXT nullable — semblanza pública (resumen biográfico).
//    - social_links  : JSON nullable — { "website", "instagram", "facebook",
//      "twitter", "linkedin", "youtube" } (mismas claves que Partner, para
//      reutilizar el componente <x-social-links> sin cambios).
//
//  Compatibilidad:
//    Migración puramente aditiva (ALTER TABLE ADD COLUMN, nullable) — no
//    afecta ninguna fila existente en role_user.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->text('public_bio')->nullable()->after('permissions');
            $table->json('social_links')->nullable()->after('public_bio');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn(['public_bio', 'social_links']);
        });
    }
};
