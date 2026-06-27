<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // ── Campos base de Laravel ──────────────────────────────────
            $table->id();                                   // BIGINT UNSIGNED AUTO_INCREMENT PK
            $table->uuid('uuid')->unique();                 // CHAR(36) UNIQUE
            $table->string('name', 150);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();                        // remember_token VARCHAR(100)

            // ── Campos extra del dominio Laguna Verde ───────────────────
            $table->string('phone', 30)->nullable()->index();
            $table->smallInteger('age')->unsigned()->nullable();
            $table->string('interest_area', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('country', 120)->nullable();

            $table->timestamps();
        });

        // Tabla auxiliar requerida por Laravel para password reset
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Tabla de sesiones (opcional, útil para auth multi-dispositivo)
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
