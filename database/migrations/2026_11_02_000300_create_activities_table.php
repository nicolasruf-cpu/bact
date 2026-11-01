<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actividades: la unidad de trabajo (WORKFLOW_ENGINE.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('type', 30);
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            // Sede donde se hace el trabajo (ADR-0008: sede de procesamiento).
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->string('priority', 10);
            $table->dateTime('available_at', 6);
            $table->dateTime('due_at', 6)->nullable();
            $table->foreignId('caused_by_activity_id')->nullable()->constrained('activities')->restrictOnDelete();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->foreignId('claimed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('claimed_at', 6)->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('completed_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            // Generación idempotente: el mismo evento no duplica actividades.
            $table->unique(['subject_type', 'subject_id', 'type', 'attempt'], 'activities_idempotent');
            $table->index(['site_id', 'type', 'status', 'priority', 'available_at'], 'activities_workbench');
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
