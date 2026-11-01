<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cultivo: proceso microbiológico de un estudio (STATE_MACHINES.md).
        Schema::create('cultures', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('sample_study_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('status', 30);
            $table->dateTime('inoculated_at', 6);
            $table->foreignId('inoculated_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['sample_study_id', 'attempt']);
        });

        Schema::create('plates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culture_id')->constrained()->restrictOnDelete();
            $table->foreignId('medium_id')->constrained('media')->restrictOnDelete();
            $table->string('inoculum', 50);
            $table->string('incubation_temperature', 30);
            $table->string('atmosphere', 20);
            $table->string('status', 20);
            $table->text('lost_reason')->nullable();
            $table->timestamps();
        });

        // Tandas (WORK_BATCHES.md).
        Schema::create('work_batches', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->string('code', 30)->unique();
            $table->string('type', 30);
            $table->foreignId('site_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('paused_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['site_id', 'owner_id', 'status']);
        });

        Schema::create('work_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('activity_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('status', 20);
            $table->text('removed_reason')->nullable();
            $table->dateTime('done_at', 6)->nullable();
            $table->timestamps();
        });

        // Una actividad en una sola tanda activa: columna generada + UNIQUE.
        DB::statement("ALTER TABLE work_batch_items ADD COLUMN active_activity_id BIGINT UNSIGNED AS (IF(status = 'pending', activity_id, NULL)) PERSISTENT");
        DB::statement('ALTER TABLE work_batch_items ADD UNIQUE INDEX work_batch_items_one_active (active_activity_id)');

        // Historial de la tanda (append-only).
        Schema::create('work_batch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_batch_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->json('data')->nullable();
            $table->dateTime('occurred_at', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_batch_events');
        Schema::dropIfExists('work_batch_items');
        Schema::dropIfExists('work_batches');
        Schema::dropIfExists('plates');
        Schema::dropIfExists('cultures');
    }
};
