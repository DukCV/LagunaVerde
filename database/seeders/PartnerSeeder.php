<?php

// ════════════════════════════════════════════════════════════════════════════
//  SEEDER: Socios colaboradores de demostración
//
//  REGLA ESTRICTA: este seeder registra EXACTAMENTE 3 socios — a propósito,
//  para mantener mínima la huella de datos de demostración en producción.
//  No se portan todos los colaboradores del mockup original (.tsx); solo
//  una muestra representativa de tipos distintos (Corporativo, Fundación, ONG).
//
//  IDEMPOTENCIA: usa firstOrCreate por nombre, igual que UserSeeder/NewsSeeder
//  — volver a ejecutar el seeder nunca duplica datos.
//
//  LOGOTIPO: los archivos físicos se cargan de forma EXTERNA (mismo patrón
//  que NewsSeeder); este seeder solo registra los metadatos en la tabla
//  'media'. Si el archivo no existe aún en el disco, la URL servida
//  simplemente devuelve 404 — no rompe el listado ni el seeder.
// ════════════════════════════════════════════════════════════════════════════

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    private const DISK = 'public';

    public function run(): void
    {
        $this->command->info('🤝 Sembrando socios colaboradores...');

        foreach ($this->partnersData() as $entry) {
            $this->seedPartnerEntry($entry);
        }

        $this->command->info('  ✓ Socios listos.');
    }

    /**
     * Catálogo mínimo de socios de prueba — EXACTAMENTE 3 registros.
     *
     * @return array<int, array<string, mixed>>
     */
    private function partnersData(): array
    {
        return [

            // ════════════════════════════════════════════════════════════
            //  SOCIO 1 — Corporativo
            // ════════════════════════════════════════════════════════════
            [
                'name'             => 'EcoSolutions Global',
                'type'             => 'Corporativo',
                'active'           => true,
                'website'          => 'https://ecosolutions.example.com',
                'social_linkedin'  => 'https://linkedin.com/company/ecosolutions',
                'social_twitter'   => 'https://twitter.com/ecosolutions',
                'who_they_are'     => 'Empresa multinacional líder en tecnología ambiental con '
                                     . 'presencia en más de 40 países, especializada en soluciones '
                                     . 'de tratamiento de agua y gestión de residuos.',
                'how_they_support' => 'Financian el 30% de los proyectos de monitoreo de calidad '
                                     . 'del agua y aportan equipos de medición de última generación '
                                     . 'para nuestras campañas científicas.',
                'logo' => [
                    'path' => 'partners/eco-solutions-global/logo.jpg',
                    'mime' => 'image/jpeg',
                    'size' => 102400,
                ],
            ],

            // ════════════════════════════════════════════════════════════
            //  SOCIO 2 — Fundación
            // ════════════════════════════════════════════════════════════
            [
                'name'             => 'Fundación Agua Limpia',
                'type'             => 'Fundación',
                'active'           => true,
                'website'          => 'https://agualimpia.example.org',
                'social_instagram' => 'https://instagram.com/funagualimpia',
                'social_facebook'  => 'https://facebook.com/funagualimpia',
                'who_they_are'     => 'Organización sin fines de lucro dedicada al acceso '
                                     . 'universal a agua potable en comunidades vulnerables, '
                                     . 'con operaciones en 12 países.',
                'how_they_support' => 'Coordinan jornadas de sensibilización comunitaria y '
                                     . 'financian la construcción de estaciones de filtración en '
                                     . 'las comunidades ribereñas aledañas a la laguna.',
                'logo' => [
                    'path' => 'partners/fundacion-agua-limpia/logo.jpg',
                    'mime' => 'image/jpeg',
                    'size' => 98304,
                ],
            ],

            // ════════════════════════════════════════════════════════════
            //  SOCIO 3 — ONG
            // ════════════════════════════════════════════════════════════
            [
                'name'             => 'Instituto Regional de Biodiversidad',
                'type'             => 'ONG',
                'active'           => true,
                'website'          => 'https://irb.example.org',
                'social_linkedin'  => 'https://linkedin.com/company/irb',
                'social_youtube'   => 'https://youtube.com/irbTV',
                'who_they_are'     => 'ONG especializada en catalogación y protección de especies '
                                     . 'amenazadas en ecosistemas lacustres de la región, operando '
                                     . 'con financiamiento internacional.',
                'how_they_support' => 'Aportan experiencia científica en monitoreo de '
                                     . 'biodiversidad, capacitan a nuestro equipo técnico y '
                                     . 'comparten sus bases de datos de especies para enriquecer '
                                     . 'nuestros reportes.',
                'logo' => [
                    'path' => 'partners/instituto-regional-biodiversidad/logo.jpg',
                    'mime' => 'image/jpeg',
                    'size' => 110592,
                ],
            ],

        ];
    }

    // ════════════════════════════════════════════════════════════════════
    //  MÉTODOS PRIVADOS
    // ════════════════════════════════════════════════════════════════════

    private function seedPartnerEntry(array $entry): void
    {
        $logo = $entry['logo'];
        unset($entry['logo']);

        $partner = Partner::firstOrCreate(['name' => $entry['name']], $entry);

        if (! $partner->wasRecentlyCreated) {
            $this->command->line("  → Ya existe, se omite: \"{$partner->name}\"");
            return;
        }

        $this->attachLogo($partner, $logo);

        $this->command->line("  + Socio creado: \"{$partner->name}\"");
    }

    /**
     * Inserta el registro de logotipo (collection='logo') asociado al socio.
     * getMorphClass() resuelve el alias 'partner' definido en el morphMap
     * de AppServiceProvider — usar Partner::class directamente lo ignoraría.
     */
    private function attachLogo(Partner $partner, array $logo): void
    {
        Media::firstOrCreate(
            [
                'mediable_type' => $partner->getMorphClass(),
                'mediable_id'   => $partner->id,
                'path'          => $logo['path'],
            ],
            [
                'collection' => 'logo',
                'disk'       => self::DISK,
                'mime'       => $logo['mime'],
                'size'       => $logo['size'],
                'title'      => 'Logo de ' . $partner->name,
                'alt'        => 'Logo de ' . $partner->name,
                'order'      => 0,
            ]
        );
    }
}
