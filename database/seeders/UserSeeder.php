<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SEEDER: Usuarios de PRUEBA para entornos locales/desarrollo
//
//  Delega a AdminUserSeeder para roles + la cuenta real de Administrador
//  (Alma) — ver ese archivo. Aquí solo se agregan TRES cuentas de prueba,
//  una por cada categoría adicional que filtra Gestión de Usuarios:
//    1. Usuario Normal     — con rol 'Usuario Normal', cuenta activa
//    2. Colaborador        — con rol 'Colaborador', cuenta activa, con un
//       perfil de socio YA vinculado (partners.user_id) — permite probar de
//       inmediato el flujo de "actualizar perfil existente" en "Administrar rol"
//    3. Usuario Inactivo   — con rol 'Usuario Normal', cuenta inhabilitada
//       (columna 'active' = false, ver migración add_active_to_users_table)
//
//  ⚠ NO ejecutar en producción: las 3 contraseñas de prueba están en texto
//    plano en este archivo, versionado en git. Usar AdminUserSeeder solo.
//
//  IDEMPOTENCIA: firstOrCreate evita duplicar datos en ejecuciones repetidas.
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    // ── Constantes de nombre de rol para evitar magic strings en todo el sistema ──
    private const ROL_USUARIO_NORMAL = 'Usuario Normal';
    private const ROL_COLABORADOR    = 'Colaborador'; // Socios/organizaciones colaboradoras

    /**
     * Crea el rol/administrador real (delegado) + las cuentas de prueba.
     */
    public function run(): void
    {
        // Roles base + Administrador (Alma) — única fuente de verdad,
        // también usada sola en producción (ver AdminUserSeeder).
        $this->call(AdminUserSeeder::class);

        $this->command->info('👤 Sembrando usuarios de prueba...');

        $rolNormal      = Role::firstOrCreate(['name' => self::ROL_USUARIO_NORMAL]);
        $rolColaborador = Role::firstOrCreate(['name' => self::ROL_COLABORADOR]);

        // ── Paso 3: Crear usuario normal ────────────────────────────────────
        $normal = User::firstOrCreate(
            // Clave de búsqueda
            ['email' => 'usuario@lagunverde.mx'],
            // Datos que se insertan SOLO si el usuario NO existe
            [
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Usuario Normal',
                'password'          => Hash::make('Password123!'), // ⚠ cambiar en producción
                'phone'             => '+52 222 100 0002',
                'age'               => 28,
                'interest_area'     => 'Ecología',
                'state'             => 'Puebla',
                'country'           => 'México',
                'profile_photo_path'=> null, // Sin foto → activa fallback de iniciales
                'active'            => true,
                'email_verified_at' => now(),
            ]
        );

        // Asigna únicamente el rol de usuario normal
        $normal->roles()->syncWithoutDetaching([$rolNormal->id]);
        $this->command->info('  ✓ Usuario Normal listo.');

        // ── Paso 4: Crear usuario Colaborador ────────────────────────────────
        $colaborador = User::firstOrCreate(
            ['email' => 'colaborador@lagunaverde.mx'],
            [
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Colaborador Laguna Verde',
                'password'          => Hash::make('Colabora123!'), // ⚠ cambiar en producción
                'phone'             => '+52 222 100 0003',
                'age'               => 31,
                'interest_area'     => 'Voluntariado',
                'state'             => 'Puebla',
                'country'           => 'México',
                'profile_photo_path'=> null, // Sin foto → activa fallback de iniciales
                'active'            => true,
                'email_verified_at' => now(),
            ]
        );

        $colaborador->roles()->syncWithoutDetaching([$rolColaborador->id]);

        // Perfil de socio ya vinculado a esta cuenta — permite probar de
        // inmediato el flujo de "actualizar perfil existente" en
        // "Administrar rol" sin tener que crearlo manualmente primero.
        Partner::firstOrCreate(
            ['user_id' => $colaborador->id],
            [
                'name'             => $colaborador->name,
                'type'             => 'Comunitario',
                'active'           => true,
                'who_they_are'     => 'Voluntario activo de Laguna Verde que apoya jornadas de limpieza y reforestación.',
                'how_they_support' => 'Organiza brigadas comunitarias y difunde las actividades del proyecto.',
            ]
        );

        $this->command->info('  ✓ Colaborador listo.');

        // ── Paso 5: Crear usuario inactivo (cuenta inhabilitada) ─────────────
        //    Permite probar el filtro "Inactivos" del panel de administración
        //    sin depender de la acción "Inhabilitar" (aún deshabilitada en la UI).
        $inactivo = User::firstOrCreate(
            ['email' => 'inactivo@lagunaverde.mx'],
            [
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Usuario Inactivo',
                'password'          => Hash::make('Inactivo123!'), // ⚠ cambiar en producción
                'phone'             => '+52 222 100 0004',
                'age'               => 40,
                'interest_area'     => 'Ecología',
                'state'             => 'Puebla',
                'country'           => 'México',
                'profile_photo_path'=> null, // Sin foto → activa fallback de iniciales
                'active'            => false,
                'email_verified_at' => now(),
            ]
        );

        $inactivo->roles()->syncWithoutDetaching([$rolNormal->id]);
        $this->command->info('  ✓ Usuario Inactivo listo.');

        $this->command->info('  ✅ Usuarios y roles sembrados correctamente.');
    }
}
