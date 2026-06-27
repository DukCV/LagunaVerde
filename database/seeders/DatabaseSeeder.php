<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,             // 1. usuarios (sin dependencias)
            CategorySeeder::class,         // 2. categorías (sin dependencias)
            NewsSeeder::class,              // 3. noticias + media (depende de users y categories)
            EventSeeder::class,             // 4. eventos + media (depende de categories)
            PartnerSeeder::class,           // 5. socios colaboradores + logo (sin dependencias)
            EventRegistrationSeeder::class, // 6. asistencias demo (depende de users y events)
        ]);
    }
}
