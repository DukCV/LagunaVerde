<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: event_registrations
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->enum('status', ['registered', 'waitlist', 'cancelled', 'attended', 'no_show'])
                  ->default('registered');

            $table->dateTime('registered_at')->useCurrent();
            $table->dateTime('checked_in_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Un usuario sólo puede registrarse una vez por evento
            $table->unique(['event_id', 'user_id']);
            $table->index('event_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
