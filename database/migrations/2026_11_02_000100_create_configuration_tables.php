<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogos clínicos versionados (MICROBIOLOGY_RULES.md §1–2).
 * El contenido clínico es configuración: nunca está escrito en el código.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('studies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_id')->constrained()->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['study_id', 'sample_type_id']);
        });

        Schema::create('protocol_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->boolean('is_reference_template')->default(false);
            $table->text('source')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('first_read_min_hours');
            $table->unsignedInteger('first_read_due_hours');
            $table->unsignedInteger('reincubation_hours')->nullable();
            $table->unsignedInteger('max_reincubations')->default(0);
            $table->boolean('allow_bulk_negative_confirmation')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('activated_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['protocol_id', 'version']);
        });

        // Una sola versión activa por protocolo: columna generada + UNIQUE (MariaDB no tiene índices parciales).
        DB::statement("ALTER TABLE protocol_versions ADD COLUMN active_protocol_id BIGINT UNSIGNED AS (IF(status = 'active', protocol_id, NULL)) PERSISTENT");
        DB::statement('ALTER TABLE protocol_versions ADD UNIQUE INDEX protocol_versions_one_active (active_protocol_id)');

        Schema::create('protocol_version_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('protocol_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('medium_id')->constrained('media')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('inoculum', 50);
            $table->string('incubation_temperature', 30);
            $table->string('atmosphere', 20);
            $table->timestamps();

            $table->unique(['protocol_version_id', 'medium_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_version_media');
        Schema::dropIfExists('protocol_versions');
        Schema::dropIfExists('protocols');
        Schema::dropIfExists('media');
        Schema::dropIfExists('studies');
        Schema::dropIfExists('sample_types');
    }
};
