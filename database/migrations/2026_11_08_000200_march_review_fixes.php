<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambios de la revisión de marchas (docs/revision/MARCHAS_2026-11-03.md):
 * - A1/A2: antibiograma con identificación presuntiva (lo habilita el protocolo) e
 *   incubación del antibiograma por panel; un antibiograma descartado deja preparar otro.
 * - A3/B7: lectura por placa, placas que se leen solo en parte del cronograma, placa perdida
 *   y resiembra.
 * - A4/C1/C3: significancia del aislamiento con «probable contaminante», editable con motivo;
 *   recuento en las lecturas sin aislamientos; informar los no significativos.
 * - A5: medios del subcultivo según el tipo de frasco.
 * - A6: texto propio para «sin desarrollo nuevo» y para la lectura intermedia.
 * - A7: carga del disco y antibiótico que se informa (prueba subrogante).
 * - B3/B4/B6/B8/B9: carga del VITEK, repique del aislamiento, identificación no concluyente,
 *   muestra no apta y derivación.
 * - C2: reglas de informe y reglas expertas que carga el laboratorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->boolean('allow_presumptive_ast')->default(false)->after('ast_read_due_hours');
            $table->boolean('report_non_significant')->default(true)->after('allow_presumptive_ast');
            $table->string('no_new_growth_label')->nullable()->after('report_non_significant');
            $table->string('intermediate_no_growth_label')->nullable()->after('no_new_growth_label');
        });

        Schema::table('protocol_version_media', function (Blueprint $table) {
            $table->unsignedSmallInteger('read_from_hours')->nullable()->after('atmosphere');
            $table->unsignedSmallInteger('read_until_hours')->nullable()->after('read_from_hours');
            $table->json('bottle_types')->nullable()->after('read_until_hours');
        });

        Schema::table('ast_panels', function (Blueprint $table) {
            $table->unsignedSmallInteger('read_min_hours')->nullable()->after('is_vitek');
            $table->unsignedSmallInteger('read_due_hours')->nullable()->after('read_min_hours');
        });

        Schema::table('antibiotics', function (Blueprint $table) {
            $table->string('disk_content', 20)->nullable()->after('name');
            $table->foreignId('reported_as_antibiotic_id')->nullable()->after('disk_content')->constrained('antibiotics')->restrictOnDelete();
        });

        Schema::table('sample_studies', function (Blueprint $table) {
            $table->dateTime('rejected_at', 6)->nullable()->after('status');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->restrictOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');
            $table->string('new_sample_requested_to')->nullable()->after('rejection_reason');
        });

        Schema::table('readings', function (Blueprint $table) {
            $table->string('label')->nullable()->after('outcome');
            $table->string('count_category', 20)->nullable()->after('label');
        });

        Schema::table('plates', function (Blueprint $table) {
            $table->foreignId('replaces_plate_id')->nullable()->after('source_plate_id')->constrained('plates')->restrictOnDelete();
            $table->foreignId('source_isolate_id')->nullable()->after('replaces_plate_id')->constrained('isolates')->restrictOnDelete();
            $table->unsignedSmallInteger('read_from_hours')->nullable()->after('atmosphere');
            $table->unsignedSmallInteger('read_until_hours')->nullable()->after('read_from_hours');
            $table->dateTime('lost_at', 6)->nullable()->after('lost_reason');
            $table->foreignId('lost_by')->nullable()->after('lost_at')->constrained('users')->restrictOnDelete();
        });

        Schema::create('plate_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reading_id')->constrained()->restrictOnDelete();
            $table->foreignId('plate_id')->constrained()->restrictOnDelete();
            $table->string('observation', 20);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['reading_id', 'plate_id']);
        });

        Schema::table('isolates', function (Blueprint $table) {
            $table->string('significance', 30)->default('significant')->after('is_significant');
            $table->text('significance_reason')->nullable()->after('significance');
        });
        DB::table('isolates')->where('is_significant', false)->update(['significance' => 'not_significant']);

        Schema::create('isolate_subcultures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->foreignId('plate_id')->constrained()->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->foreignId('subcultured_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('subcultured_at', 6);
            $table->string('result', 20)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('read_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::create('instrument_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->string('instrument', 50);
            $table->string('id_card_type', 30)->nullable();
            $table->string('id_card_number', 50)->nullable();
            $table->string('ast_card_type', 30)->nullable();
            $table->string('ast_card_number', 50)->nullable();
            $table->string('cassette', 30)->nullable();
            $table->string('position', 10)->nullable();
            $table->string('suspension', 50)->nullable();
            $table->foreignId('loaded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('loaded_at', 6);
            $table->dateTime('id_result_at', 6)->nullable();
            $table->dateTime('ast_result_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::table('identifications', function (Blueprint $table) {
            $table->foreignId('organism_id')->nullable()->change();
            $table->foreignId('instrument_run_id')->nullable()->after('organism_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable()->after('tests');
        });

        Schema::table('susceptibility_tests', function (Blueprint $table) {
            $table->foreignId('instrument_run_id')->nullable()->after('ast_panel_id')->constrained()->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('read_at')->constrained('users')->restrictOnDelete();
            $table->dateTime('cancelled_at', 6)->nullable()->after('cancelled_by');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->index('isolate_id', 'susceptibility_tests_isolate_index');
        });
        Schema::table('susceptibility_tests', function (Blueprint $table) {
            $table->dropUnique(['isolate_id']);
        });

        Schema::table('susceptibility_results', function (Blueprint $table) {
            $table->text('rule_notes')->nullable()->after('override_reason');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->string('destination');
            $table->string('reason', 500);
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('sent_at', 6);
            $table->text('result')->nullable();
            $table->foreignId('result_recorded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('result_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::create('ast_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('effect', 20);
            $table->string('interpretation', 2)->nullable();
            $table->text('comment')->nullable();
            $table->json('organism_groups');
            $table->foreignId('identification_test_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('test_result_code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_reference_template')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('ast_rule_antibiotics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ast_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('antibiotic_id')->constrained()->restrictOnDelete();
            $table->unique(['ast_rule_id', 'antibiotic_id']);
        });

        Schema::create('ast_rule_sample_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ast_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained()->restrictOnDelete();
            $table->unique(['ast_rule_id', 'sample_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_rule_sample_types');
        Schema::dropIfExists('ast_rule_antibiotics');
        Schema::dropIfExists('ast_rules');
        Schema::dropIfExists('referrals');

        Schema::table('susceptibility_results', function (Blueprint $table) {
            $table->dropColumn('rule_notes');
        });
        Schema::table('susceptibility_tests', function (Blueprint $table) {
            $table->unique('isolate_id');
        });
        Schema::table('susceptibility_tests', function (Blueprint $table) {
            $table->dropIndex('susceptibility_tests_isolate_index');
            $table->dropConstrainedForeignId('instrument_run_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'cancellation_reason']);
        });
        Schema::table('identifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('instrument_run_id');
            $table->dropColumn('notes');
        });

        Schema::dropIfExists('instrument_runs');
        Schema::dropIfExists('isolate_subcultures');

        Schema::table('isolates', function (Blueprint $table) {
            $table->dropColumn(['significance', 'significance_reason']);
        });

        Schema::dropIfExists('plate_readings');

        Schema::table('plates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaces_plate_id');
            $table->dropConstrainedForeignId('source_isolate_id');
            $table->dropConstrainedForeignId('lost_by');
            $table->dropColumn(['read_from_hours', 'read_until_hours', 'lost_at']);
        });
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['label', 'count_category']);
        });
        Schema::table('sample_studies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['rejected_at', 'rejection_reason', 'new_sample_requested_to']);
        });
        Schema::table('antibiotics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reported_as_antibiotic_id');
            $table->dropColumn('disk_content');
        });
        Schema::table('ast_panels', function (Blueprint $table) {
            $table->dropColumn(['read_min_hours', 'read_due_hours']);
        });
        Schema::table('protocol_version_media', function (Blueprint $table) {
            $table->dropColumn(['read_from_hours', 'read_until_hours', 'bottle_types']);
        });
        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->dropColumn(['allow_presumptive_ast', 'report_non_significant', 'no_new_growth_label', 'intermediate_no_growth_label']);
        });
    }
};
