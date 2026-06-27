<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: donations
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Usuario opcional (donante puede ser anónimo / sin cuenta)
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->string('donor_name', 150);
            $table->string('phone', 30)->nullable();

            // DECIMAL(12,2): hasta 9 999 999 999.99
            $table->decimal('amount', 12, 2);

            $table->string('payment_reference', 190)->unique();
            $table->string('payment_method', 60)->nullable();

            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])
                  ->default('pending');

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
