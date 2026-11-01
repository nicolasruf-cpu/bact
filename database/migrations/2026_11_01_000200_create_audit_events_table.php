<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría append-only con hash encadenado (ADR-0006).
 * Los triggers impiden UPDATE y DELETE incluso por fuera de la aplicación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->dateTime('occurred_at', 6);
            $table->string('action', 100);
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_id', 36)->nullable();
            $table->char('previous_hash', 64)->nullable();
            $table->char('hash', 64)->unique();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });

        // Cabeza de la cadena: una sola fila que se bloquea (FOR UPDATE) al escribir,
        // para que dos eventos simultáneos no tomen el mismo hash anterior.
        Schema::create('audit_chain_head', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->char('last_hash', 64)->nullable();
        });

        DB::table('audit_chain_head')->insert(['id' => 1]);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events es append-only: UPDATE no permitido'
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events es append-only: DELETE no permitido'
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_no_delete');
        Schema::dropIfExists('audit_chain_head');
        Schema::dropIfExists('audit_events');
    }
};
