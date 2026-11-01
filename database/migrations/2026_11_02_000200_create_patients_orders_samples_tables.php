<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Datos mínimos necesarios del paciente (SECURITY.md).
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('document_number', 20)->index();
            $table->string('last_name');
            $table->string('first_name');
            $table->date('birth_date')->nullable();
            $table->string('sex', 10);
            $table->string('external_id', 50)->nullable()->index();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
        });

        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            // Número de orden del LIS (LABWIN). Único por sede; varias órdenes sin número son posibles.
            $table->string('external_number', 50)->nullable();
            $table->string('requesting_service', 100)->nullable();
            $table->string('requesting_physician', 150)->nullable();
            $table->string('priority', 10);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'external_number']);
        });

        // Recepción = una entrega física (una o varias muestras de una orden).
        // La idempotencia se controla aquí: un reintento no duplica la entrega.
        Schema::create('receptions', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('lab_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->dateTime('received_at', 6);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->char('idempotency_key', 36)->unique();
            $table->timestamps();
        });

        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('barcode', 50)->unique();
            $table->foreignId('lab_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('reception_id')->constrained()->restrictOnDelete();
            $table->foreignId('origin_site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->dateTime('collected_at', 6)->nullable();
            $table->dateTime('received_at', 6);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('reception_notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['origin_site_id', 'status']);
        });

        Schema::create('sample_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_id')->constrained()->restrictOnDelete();
            // Versión de protocolo congelada al recibir la muestra (MICROBIOLOGY_RULES §1).
            $table->foreignId('protocol_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('processing_site_id')->constrained('sites')->restrictOnDelete();
            $table->string('status', 30);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['sample_id', 'study_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_studies');
        Schema::dropIfExists('samples');
        Schema::dropIfExists('receptions');
        Schema::dropIfExists('lab_orders');
        Schema::dropIfExists('patients');
    }
};
