<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🗂️  Sembrando categorías...');

        // ════════════════════════════════════════════════════════════════
        //  CATEGORÍAS INICIALES
        //
        //  Cada entrada es ['type' => '...', 'name' => '...'].
        //  firstOrCreate evita duplicados al re-ejecutar.
        //
        //  ➕ Para agregar más, añade una línea al array del tipo
        //     correspondiente o agrega un nuevo bloque de tipo.
        // ════════════════════════════════════════════════════════════════

        // ── Noticias ─────────────────────────────────────────────────────
        $newsCategories = [
            'General',
            'Medio Ambiente',   // ← usada en NewsSeeder
            'Comunidad',
            'Educación',
            'Proyectos',
        ];

        foreach ($newsCategories as $name) {
            Category::firstOrCreate(['type' => 'news', 'name' => $name]);
        }

        // ── Eventos ──────────────────────────────────────────────────────
        $eventCategories = [
            'Taller',
            'Conferencia',
            'Reforestación',
            'Limpieza',
            'Exposición',
        ];

        foreach ($eventCategories as $name) {
            Category::firstOrCreate(['type' => 'events', 'name' => $name]);
        }

        // ── Proyectos ────────────────────────────────────────────────────
        $projectCategories = [
            'Conservación',
            'Educación Ambiental',
            'Investigación',
            'Infraestructura',
        ];

        foreach ($projectCategories as $name) {
            Category::firstOrCreate(['type' => 'projects', 'name' => $name]);
        }

        // ── ➕ Agrega más categorías aquí ─────────────────────────────────
        // Category::firstOrCreate(['type' => 'news',     'name' => 'Voluntariado']);
        // Category::firstOrCreate(['type' => 'events',   'name' => 'Feria']);
        // Category::firstOrCreate(['type' => 'projects', 'name' => 'Restauración']);

        $this->command->info('  ✓ Categorías listas.');
    }
}
