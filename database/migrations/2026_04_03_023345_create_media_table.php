<?php
// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: media  (polimórfica — events, news, projects)
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            // Relación polimórfica
            $table->string('mediable_type', 160);
            $table->unsignedBigInteger('mediable_id');

            $table->string('disk', 40);           // local | s3 | public …
            $table->string('path', 500);
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');   // bytes
            $table->string('title', 200)->nullable();
            $table->string('alt', 250)->nullable();
            $table->integer('order')->nullable();

            $table->timestamps();

            $table->index(['mediable_type', 'mediable_id'], 'idx_media_mediable');
            $table->index(['disk', 'path'], 'idx_media_disk_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
