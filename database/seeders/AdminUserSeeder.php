<?php

// ══════════════════════════════════════════════════════════════════════════════
//  SEEDER: Roles base + cuenta real de Administrador (Alma)
//
//  ÚNICO seeder de usuarios seguro para producción: a diferencia de
//  UserSeeder (solo entornos locales/desarrollo), este NO crea cuentas de
//  prueba (Usuario Normal/Colaborador/Inactivo) — esas tienen contraseñas
//  de demostración conocidas, versionadas en este mismo repositorio.
//
//  UserSeeder delega aquí para la parte del Administrador (DRY) y luego
//  agrega sus propias cuentas de prueba encima.
//
//  SEGURIDAD:
//    ⚠ La contraseña sembrada aquí también está en texto plano en este
//      archivo (versionado en git). Cámbiala manualmente después del
//      primer despliegue a producción (Mi Perfil → Cambiar contraseña,
//      cuando esa función se active, o vía tinker mientras tanto).
// ══════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminRoleService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    private const ROL_ADMINISTRADOR  = 'Administrador';
    private const ROL_USUARIO_NORMAL = 'Usuario Normal';
    private const ROL_COLABORADOR    = 'Colaborador';

    public function run(): void
    {
        $this->command->info('👤 Sembrando roles base y administrador...');

        // Los 3 roles deben existir siempre, aunque aquí solo se cree un usuario:
        // el registro público asigna 'Usuario Normal', y "Administrar rol" en el
        // panel asigna cualquiera de los tres.
        $rolAdmin = Role::firstOrCreate(['name' => self::ROL_ADMINISTRADOR]);
        Role::firstOrCreate(['name' => self::ROL_USUARIO_NORMAL]);
        Role::firstOrCreate(['name' => self::ROL_COLABORADOR]);

        $admin = User::firstOrCreate(
            ['email' => 'Almariosf@hotmail.com'],
            [
                'uuid'              => (string) Str::uuid(),
                'name'              => 'Administrador Laguna Verde',
                'password'          => Hash::make('lcc5OcKq4onrJLKt'), // ⚠ cambiar tras el primer deploy
                'phone'             => '+52 222 100 0001',
                'age'               => 35,
                'interest_area'     => 'Administración',
                'state'             => 'Puebla',
                'country'           => 'México',
                'profile_photo_path'=> null,
                'active'            => true,
                'email_verified_at' => now(),
            ]
        );

        // Catálogo de permisos resuelto desde AdminRoleService — evita
        // duplicar las claves de permiso como magic strings en el seeder.
        $todosLosPermisos = array_merge(
            ...array_values(app(AdminRoleService::class)->getPermissionCatalog())
        );

        // 'permissions'/'social_links' como array PHP plano (nunca pre-codificado
        // a JSON): el pivote tiene clase propia (RoleUser) y su cast 'array' ya
        // serializa al guardar — codificarlo aquí también produciría doble JSON.
        $admin->roles()->syncWithoutDetaching([
            $rolAdmin->id => [
                'position'     => 'Director General',
                'permissions'  => array_keys($todosLosPermisos),
                'public_bio'   => 'Biólogo ambiental con más de 15 años de experiencia en '
                    . 'conservación de ecosistemas acuáticos. Lidera la estrategia de Laguna '
                    . 'Verde desde su fundación, impulsando proyectos de restauración y '
                    . 'educación ambiental en la región.',
                'social_links' => [
                    'linkedin'  => 'https://www.linkedin.com/in/administrador-laguna-verde',
                    'twitter'   => 'https://twitter.com/lagunaverde',
                    'facebook'  => 'https://www.facebook.com/lagunaverde',
                ],
                'show_in_about_us' => true,
            ],
        ]);

        $this->command->info('  ✓ Administrador listo.');
    }
}
