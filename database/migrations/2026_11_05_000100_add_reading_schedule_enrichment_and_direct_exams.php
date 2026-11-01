<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resto de los cultivos: cronograma de lecturas por protocolo, caldos de enriquecimiento
 * con repique a placa y examen directo de la muestra (D-0017).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_versions', function (Blueprint $table) {
            // Lecturas programadas desde la siembra: [{min_hours, due_hours}, …]. Null = una sola lectura.
            $table->json('reading_schedule')->nullable()->after('first_read_due_hours');
            $table->json('direct_exam_settings')->nullable()->after('blood_culture_settings');
        });

        Schema::table('protocol_version_media', function (Blueprint $table) {
            $table->boolean('is_enrichment')->default(false)->after('atmosphere');
            $table->unsignedSmallInteger('enrichment_subculture_min_hours')->nullable()->after('is_enrichment');
            $table->unsignedSmallInteger('enrichment_subculture_due_hours')->nullable()->after('enrichment_subculture_min_hours');
            // Medios del repique: [{medium_code, incubation_temperature, atmosphere}, …].
            $table->json('enrichment_subculture_media')->nullable()->after('enrichment_subculture_due_hours');
        });

        Schema::table('plates', function (Blueprint $table) {
            $table->boolean('is_enrichment')->default(false)->after('atmosphere');
            $table->foreignId('source_plate_id')->nullable()->after('culture_id')->constrained('plates')->restrictOnDelete();
            $table->dateTime('inoculated_at', 6)->nullable()->after('status');
        });

        Schema::create('direct_exams', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('sample_study_id')->unique()->constrained()->restrictOnDelete();
            $table->json('results');
            $table->text('description')->nullable();
            $table->boolean('organisms_seen')->default(false);
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('performed_at', 6);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direct_exams');

        Schema::table('plates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_plate_id');
            $table->dropColumn(['is_enrichment', 'inoculated_at']);
        });

        Schema::table('protocol_version_media', function (Blueprint $table) {
            $table->dropColumn(['is_enrichment', 'enrichment_subculture_min_hours', 'enrichment_subculture_due_hours', 'enrichment_subculture_media']);
        });

        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->dropColumn(['reading_schedule', 'direct_exam_settings']);
        });
    }
};
