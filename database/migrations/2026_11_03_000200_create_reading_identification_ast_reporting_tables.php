<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- Catálogos (Configuration) -------------------------------------------------
        Schema::create('organisms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('organism_group', 40)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('antibiotics', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Paneles por grupo de organismo (versionados con la plantilla; D-0008).
        Schema::create('ast_panels', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('organism_group', 40)->index();
            $table->boolean('is_reference_template')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ast_panel_antibiotics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ast_panel_id')->constrained()->restrictOnDelete();
            $table->foreignId('antibiotic_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unique(['ast_panel_id', 'antibiotic_id']);
        });

        // Puntos de corte: los carga el laboratorio desde su edición del M100 (Q-C4).
        Schema::create('breakpoint_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('edition', 50);
            $table->string('status', 20);
            // true = set de demostración con valores ficticios: nunca en producción.
            $table->boolean('is_synthetic')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('approved_at', 6)->nullable();
            $table->timestamps();
        });

        Schema::create('breakpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('breakpoint_set_id')->constrained()->restrictOnDelete();
            $table->string('organism_group', 40);
            $table->foreignId('antibiotic_id')->constrained()->restrictOnDelete();
            $table->string('method', 10);
            // Difusión: S si halo ≥ susceptible_value; R si ≤ resistant_value.
            // CIM:     S si CIM ≤ susceptible_value; R si ≥ resistant_value. Entre ambos: I.
            $table->decimal('susceptible_value', 8, 3);
            $table->decimal('resistant_value', 8, 3);
            $table->timestamps();

            $table->unique(['breakpoint_set_id', 'organism_group', 'antibiotic_id', 'method'], 'breakpoints_unique');
        });

        // --- Lectura y aislamientos (Cultures) --------------------------------------
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culture_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('option_code', 20);
            $table->string('outcome', 30);
            $table->text('notes')->nullable();
            $table->foreignId('read_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('read_at', 6);
            $table->timestamps();

            $table->unique(['culture_id', 'attempt']);
        });

        Schema::create('isolates', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('culture_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('morphotype', 255);
            $table->string('count_category', 20)->nullable();
            $table->foreignId('plate_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_significant');
            $table->string('status', 30);
            $table->foreignId('organism_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('no_ast_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['culture_id', 'number']);
        });

        // --- Identificación ----------------------------------------------------------
        Schema::create('identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->string('method', 20);
            $table->foreignId('organism_id')->constrained()->restrictOnDelete();
            $table->string('level', 20);
            $table->string('confidence', 30)->nullable();
            $table->text('tests')->nullable();
            $table->foreignId('identified_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('identified_at', 6);
            $table->timestamps();
        });

        // --- Antibiograma ------------------------------------------------------------
        Schema::create('susceptibility_tests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->foreignId('ast_panel_id')->constrained()->restrictOnDelete();
            $table->string('method', 10);
            // Set de puntos de corte congelado al interpretar (reproducibilidad).
            $table->foreignId('breakpoint_set_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->foreignId('set_up_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('set_up_at', 6);
            $table->foreignId('read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('read_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique('isolate_id');
        });

        Schema::create('susceptibility_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('susceptibility_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('antibiotic_id')->constrained()->restrictOnDelete();
            $table->string('mic_operator', 2)->nullable();
            $table->decimal('measured_value', 8, 3)->nullable();
            $table->string('automatic_interpretation', 2)->nullable();
            $table->string('reviewed_interpretation', 2)->nullable();
            $table->text('override_reason')->nullable();
            $table->boolean('is_reported')->default(true);
            $table->timestamps();

            $table->unique(['susceptibility_test_id', 'antibiotic_id'], 'susceptibility_results_unique');
        });

        // --- Informes (Reporting) ----------------------------------------------------
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('sample_study_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('type', 20);
            $table->string('status', 20);
            // Contenido inmutable del informe validado.
            $table->json('snapshot');
            $table->foreignId('validated_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('validated_at', 6);
            $table->timestamps();

            $table->unique(['sample_study_id', 'version']);
        });
    }

    public function down(): void
    {
        foreach (['reports', 'susceptibility_results', 'susceptibility_tests', 'identifications', 'isolates', 'readings',
            'breakpoints', 'breakpoint_sets', 'ast_panel_antibiotics', 'ast_panels', 'antibiotics', 'organisms'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
