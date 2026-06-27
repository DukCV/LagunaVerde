<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: Agrega "puesto" y "permisos" a la tabla pivote role_user
//
//  Propósito:
//    Estos dos atributos pertenecen a una asignación de rol CONCRETA (la fila
//    de role_user), no al usuario ni al rol en general:
//      - position    : puesto del usuario en esa asignación (ej. "Director
//                       General"). Solo tiene sentido para el rol Administrador,
//                       pero vive en el pivote porque describe ESA asignación.
//      - permissions : permisos granulares otorgados en esa asignación
//                       específica (lista blanca fija, ver
//                       AdminRoleService::PERMISOS_DISPONIBLES), guardados
//                       como JSON. Permite que dos administradores tengan
//                       conjuntos de permisos distintos sin necesitar una
//                       tabla adicional — el catálogo de permisos es fijo y
//                       pequeño (10 claves), por lo que JSON es más liviano
//                       que normalizar en una tabla nueva en este hosting
//                       compartido.
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
            $table->string('position', 100)->nullable()->after('role_id');
            $table->json('permissions')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn(['position', 'permissions']);
        });
    }
};
