<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hemocultivo y retrocultivo (Fase 4b): un frasco por muestra, procesado por equipo
 * automatizado o en forma manual con repiques; Gram del positivo y comunicación del
 * resultado crítico. Ver docs/templates/HEMOCULTIVO.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_versions', function (Blueprint $table) {
            // plate = cultivo en placas (urocultivo); blood_bottle = frasco de hemocultivo.
            $table->string('processing_kind', 20)->default('plate')->after('is_reference_template');
            // Parámetros del frasco (días de incubación, repiques, tipos de frasco, opciones de Gram).
            $table->json('blood_culture_settings')->nullable()->after('count_categories');
        });

        Schema::create('blood_culture_bottles', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            // Un frasco por muestra (respuesta del laboratorio, 2026-11-01).
            $table->foreignId('sample_study_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method', 20);
            $table->string('bottle_type', 20);
            $table->string('instrument', 50)->nullable();
            $table->string('instrument_position', 30)->nullable();
            $table->dateTime('loaded_at', 6);
            $table->foreignId('loaded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('incubation_ends_at', 6);
            $table->string('status', 20);
            $table->dateTime('positive_at', 6)->nullable();
            $table->string('positivity_source', 20)->nullable();
            $table->foreignId('positive_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('closed_at', 6)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        // Repiques del procesamiento manual: se repica a placa y la placa se lee después.
        Schema::create('blind_subcultures', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('blood_culture_bottle_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('medium_id')->constrained('media')->restrictOnDelete();
            $table->dateTime('subcultured_at', 6);
            $table->foreignId('subcultured_by')->constrained('users')->restrictOnDelete();
            $table->string('result', 20)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('read_at', 6)->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['blood_culture_bottle_id', 'number']);
        });

        Schema::create('gram_stains', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('blood_culture_bottle_id')->unique()->constrained()->restrictOnDelete();
            $table->string('option_code', 20);
            $table->text('description')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('performed_at', 6);
            $table->timestamps();
        });

        // Resultados críticos (STATE_MACHINES.md: CriticalAlert) y sus intentos de comunicación.
        Schema::create('critical_alerts', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('sample_study_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('source', 30);
            $table->string('source_key', 60)->unique();
            $table->text('summary');
            $table->string('status', 20);
            $table->dateTime('opened_at', 6);
            $table->dateTime('closed_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('critical_communications', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('critical_alert_id')->constrained()->restrictOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_role')->nullable();
            $table->string('channel', 20);
            $table->boolean('successful');
            $table->boolean('read_back_confirmed')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('communicated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('communicated_at', 6);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('critical_communications');
        Schema::dropIfExists('critical_alerts');
        Schema::dropIfExists('gram_stains');
        Schema::dropIfExists('blind_subcultures');
        Schema::dropIfExists('blood_culture_bottles');
        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->dropColumn(['processing_kind', 'blood_culture_settings']);
        });
    }
};
