<?php

// ══════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Agrega columna de foto de perfil a la tabla de usuarios
//
//  Propósito:
//    Permite almacenar la ruta relativa de la foto de perfil del usuario.
//    Es nullable para que usuarios sin foto activen el fallback de iniciales.
//
//  Compatibilidad Hostinger:
//    Usa FILESYSTEM_DISK=public (ya configurado en .env). Las fotos se guardan
//    en storage/app/public/profile-photos/ y se sirven vía storage link.
// ══════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega la columna al esquema existente sin romper datos previos.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ruta relativa dentro del disco 'public' (ej: profile-photos/uuid.jpg)
            // Nullable: usuarios sin foto usan el badge de iniciales en el header
            $table->string('profile_photo_path', 2048)->nullable()->after('country');
        });
    }

    /**
     * Elimina la columna de forma segura al revertir.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_photo_path');
        });
    }
};
