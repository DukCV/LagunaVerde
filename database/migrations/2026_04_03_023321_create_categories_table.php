<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: categories  (reutilizable para news, events y projects)
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('type', ['news', 'events', 'projects']);
            $table->timestamps();

            // Composite unique: el mismo nombre puede existir en distintos types
            $table->unique(['type', 'name']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
