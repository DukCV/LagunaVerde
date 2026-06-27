<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: events
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 180);
            $table->text('description');

            // Dirección o nombre del lugar del evento
            // Usada en la página de detalle para mostrar texto y mapa
            $table->string('location', 300)->nullable();

            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('capacity_total')->default(0);
            $table->longText('content')->nullable();
            $table->enum('status', ['draft', 'published', 'cancelled', 'closed'])
                  ->default('draft');

            $table->timestamps();

            $table->index('category_id');
            $table->index('start_at');
            $table->index('end_at');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
