<?php

// ════════════════════════════════════════════════════════════════════════════
//  MIGRACIÓN: partners (socios colaboradores — organizaciones, no usuarios)
//
//  AUDITORÍA PREVIA:
//   No existía ninguna tabla 'partners' ni 'collaborators' en el esquema.
//   Lo único relacionado era el rol 'Colaborador' asignado a usuarios
//   (App\Models\User), un concepto distinto: cuentas con acceso de login.
//   El diseño de socios (logo, tipo de organización, redes sociales,
//   "quiénes son" / "cómo apoyan") corresponde a organizaciones públicas
//   que NO inician sesión, por lo que se crea una tabla nueva e
//   independiente — sin tocar la tabla 'users' ni el rol 'Colaborador'.
//
//   Esta migración es de creación pura (CREATE TABLE): no modifica ni
//   elimina ninguna tabla ni columna existente — cero riesgo para los
//   datos ya presentes en la base de datos de producción.
//
//  LOGOTIPO:
//   Se reutiliza la tabla polimórfica 'media' ya existente (collection
//   'logo') en lugar de añadir una columna de imagen — evita duplicar
//   lógica de almacenamiento ya resuelta para noticias/eventos.
// ════════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();

            $table->string('name', 150);

            // Tipo de organización — lista blanca validada en código
            // (App\Models\Partner::TYPES). Se usa string en vez de enum de BD
            // para poder agregar tipos nuevos sin necesitar otra migración.
            $table->string('type', 30);

            // Visibilidad pública: controla si el socio aparece en el sitio.
            $table->boolean('active')->default(true);

            // Enlaces — todos opcionales, validados como http(s) en el formulario.
            $table->string('website', 255)->nullable();
            $table->string('social_instagram', 255)->nullable();
            $table->string('social_facebook', 255)->nullable();
            $table->string('social_twitter', 255)->nullable();
            $table->string('social_linkedin', 255)->nullable();
            $table->string('social_youtube', 255)->nullable();

            $table->text('who_they_are');
            $table->text('how_they_support');

            $table->timestamps();

            // Optimiza la consulta pública más frecuente:
            // WHERE active = 1 ORDER BY created_at DESC
            $table->index(['active', 'created_at'], 'idx_partners_active_created');

            // Optimiza el filtro por tipo en el listado administrativo
            $table->index('type', 'idx_partners_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
