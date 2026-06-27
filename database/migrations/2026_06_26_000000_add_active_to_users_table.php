<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Agrega columna de estado activo/inactivo a la tabla de usuarios
//
//  Propósito:
//    Permite que el panel de administración distinga cuentas activas de
//    cuentas inhabilitadas sin depender del sistema de roles. Es la base de
//    datos del filtro "Inactivos" en Gestión de Usuarios — la acción de
//    inhabilitar en sí permanece deshabilitada en esta iteración (ver
//    App\Livewire\Admin\UsersManagement).
//
//  Valor por defecto:
//    true — todos los usuarios existentes se consideran activos al aplicar
//    esta migración; ninguna cuenta queda bloqueada accidentalmente.
//
//  Índice compuesto:
//    Mismo patrón que 'partners.idx_partners_active_created' — optimiza el
//    filtro "Inactivos" (WHERE active = 0 ORDER BY created_at) en el listado
//    administrativo paginado.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('profile_photo_path');

            $table->index(['active', 'created_at'], 'idx_users_active_created');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_active_created');
            $table->dropColumn('active');
        });
    }
};
