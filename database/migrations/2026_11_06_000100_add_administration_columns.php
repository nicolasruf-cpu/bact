<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administración (Fase 4d): usuarios activos, Director técnico responsable de la
 * configuración clínica (Q-A8), concurrencia en catálogos y ciclo de vida de los sets
 * de puntos de corte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            // Único Director técnico que aprueba la configuración clínica compartida (Q-A8).
            $table->boolean('is_clinical_approver')->default(false)->after('is_active');
            $table->unsignedInteger('lock_version')->default(0)->after('remember_token');
        });

        // A lo sumo un responsable: columna generada + UNIQUE (MariaDB no tiene índices parciales).
        DB::statement('ALTER TABLE users ADD COLUMN clinical_approver_key TINYINT AS (IF(is_clinical_approver, 1, NULL)) PERSISTENT');
        DB::statement('ALTER TABLE users ADD UNIQUE INDEX users_one_clinical_approver (clinical_approver_key)');

        foreach (['sample_types', 'studies', 'media', 'organisms', 'antibiotics'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedInteger('lock_version')->default(0);
            });
        }

        Schema::table('breakpoint_sets', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('edition');
            $table->foreignId('created_by')->nullable()->after('is_synthetic')->constrained('users')->restrictOnDelete();
            $table->dateTime('activated_at', 6)->nullable()->after('approved_at');
            $table->dateTime('retired_at', 6)->nullable()->after('activated_at');
            $table->unsignedInteger('lock_version')->default(0);
        });

        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('ast_read_due_hours')->constrained('users')->restrictOnDelete();
            $table->dateTime('retired_at', 6)->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('protocol_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('retired_at');
        });

        Schema::table('breakpoint_sets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['notes', 'activated_at', 'retired_at', 'lock_version']);
        });

        foreach (['sample_types', 'studies', 'media', 'organisms', 'antibiotics'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('lock_version'));
        }

        DB::statement('ALTER TABLE users DROP INDEX users_one_clinical_approver');
        DB::statement('ALTER TABLE users DROP COLUMN clinical_approver_key');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_clinical_approver', 'lock_version']);
        });
    }
};
