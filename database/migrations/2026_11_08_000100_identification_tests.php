<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pruebas de identificación (revisión de marchas B1, B2, B5): catálogo que carga el
 * laboratorio (Gram de la colonia, pruebas rápidas, bioquímicas, complementarias y de
 * mecanismo) y el registro de cada prueba hecha sobre un aislamiento, con su siembra,
 * su lectura y su resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identification_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('kind', 20)->index();
            $table->json('result_options');
            $table->unsignedSmallInteger('incubation_hours')->nullable();
            $table->boolean('is_reportable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_reference_template')->default(false);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('isolate_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolate_id')->constrained()->restrictOnDelete();
            $table->foreignId('identification_test_id')->constrained()->restrictOnDelete();
            $table->foreignId('susceptibility_test_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('set_up_at');
            $table->foreignId('set_up_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('readable_at');
            $table->string('result_code', 20)->nullable();
            $table->string('result_label')->nullable();
            $table->string('notes', 500)->nullable();
            $table->dateTime('read_at')->nullable();
            $table->foreignId('read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['isolate_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isolate_tests');
        Schema::dropIfExists('identification_tests');
    }
};
