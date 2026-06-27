<?php

namespace App\Livewire\About;

use App\DTOs\TeamMemberDto;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

/**
 * Componente Livewire: sección "Nuestro Equipo" de la página pública
 * "Quiénes Somos".
 *
 * Muestra a los Administradores ACTIVOS que hayan optado por mostrarse
 * (role_user.show_in_about_us = true, ver App\Livewire\Admin\MyProfile),
 * con su foto, puesto, semblanza pública y redes sociales — datos que
 * ellos mismos llenan desde "Mi Perfil" en el panel de administración.
 *
 * RENDIMIENTO:
 *  - select() mínimo + with('roles', ...) evita el problema N+1: una sola
 *    consulta adicional carga el rol y su pivote (position/public_bio/
 *    social_links) de TODOS los administradores a la vez, en vez de una
 *    consulta por fila.
 *  - Cache::remember(): la composición del equipo de administradores
 *    cambia con muy poca frecuencia, así que se cachea por
 *    self::CACHE_TTL_MINUTOS para evitar repetir esta consulta en cada
 *    visita a "Quiénes Somos" — reduce carga de BD en el hosting compartido.
 *    Compensación aceptada: un cambio de semblanza/foto puede tardar hasta
 *    ese tiempo en reflejarse públicamente (no hay invalidación activa de
 *    caché desde UserRoleManager — fuera del alcance de esta iteración).
 */
class TeamSection extends Component
{
    /** @var array[] Cada elemento es un TeamMemberDto serializado vía toLivewire() */
    public array $equipo = [];

    // Nombre del rol — magic string local, igual criterio que
    // Database\Seeders\UserSeeder (no se reutiliza una constante del panel
    // admin desde código público para no acoplar ambos contextos).
    private const ROL_ADMINISTRADOR = 'Administrador';

    private const CACHE_KEY          = 'about.equipo-administradores';
    private const CACHE_TTL_MINUTOS  = 60;

    // ── Columnas mínimas necesarias para TeamMemberDto::fromModel() ──────
    private const COLUMNS = ['id', 'name', 'profile_photo_path'];

    public function mount(): void
    {
        $this->equipo = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTOS),
            fn () => $this->cargarAdministradoresActivos(),
        );
    }

    /**
     * Carga los administradores activos Y con visibilidad pública activada
     * desde la BD, ordenados por nombre. with('roles', ...) restringido al
     * rol 'Administrador' carga también su pivote (position/public_bio/
     * social_links) en la misma consulta.
     *
     * El filtro de role_user.show_in_about_us se aplica en ambos lados:
     *  - whereHas(): aquí el closure recibe el Builder del modelo Role con
     *    la tabla pivote 'role_user' YA unida (join) por la propia
     *    resolución de la relación — por eso se referencia calificada
     *    ('role_user.show_in_about_us') en vez de wherePivot().
     *  - with(): aquí el closure SÍ recibe la relación BelongsToMany en sí
     *    (no un Builder plano), de modo que wherePivot() está disponible
     *    directamente — evita cargar administradores que decidieron no
     *    mostrarse, aunque ya hayan pasado el whereHas().
     */
    private function cargarAdministradoresActivos(): array
    {
        return User::query()
            ->select(self::COLUMNS)
            ->where('active', true)
            ->whereHas('roles', fn ($q) => $q
                ->where('name', self::ROL_ADMINISTRADOR)
                ->where('role_user.show_in_about_us', true))
            ->with(['roles' => fn ($q) => $q
                ->where('name', self::ROL_ADMINISTRADOR)
                ->wherePivot('show_in_about_us', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (User $usuario) => TeamMemberDto::fromModel($usuario)->toLivewire())
            ->all();
    }

    public function render()
    {
        return view('livewire.about.team-section');
    }
}
