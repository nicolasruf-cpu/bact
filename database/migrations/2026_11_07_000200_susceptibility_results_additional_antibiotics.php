<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Antibióticos por fuera del panel (D-0022): cada resultado tiene su método, su hora de
 * preparación y su lectura. Los adicionales se agregan al preparar o al leer el panel; los
 * agregados al leer tienen su propia lectura. Los resultados existentes toman el método y
 * las horas de su antibiograma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('susceptibility_results', function (Blueprint $table) {
            $table->string('method', 10)->nullable()->after('antibiotic_id');
            $table->boolean('is_additional')->default(false)->after('method');
            $table->text('addition_reason')->nullable()->after('is_additional');
            $table->dateTime('set_up_at', 6)->nullable()->after('addition_reason');
            $table->foreignId('set_up_by')->nullable()->after('set_up_at')->constrained('users')->restrictOnDelete();
            $table->dateTime('read_at', 6)->nullable()->after('is_reported');
        });

        DB::statement('UPDATE susceptibility_results r JOIN susceptibility_tests t ON t.id = r.susceptibility_test_id
            SET r.method = t.method, r.set_up_at = t.set_up_at, r.set_up_by = t.set_up_by, r.read_at = t.read_at');

        Schema::table('susceptibility_results', function (Blueprint $table) {
            $table->string('method', 10)->nullable(false)->change();
            $table->dateTime('set_up_at', 6)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('susceptibility_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('set_up_by');
            $table->dropColumn(['method', 'is_additional', 'addition_reason', 'set_up_at', 'read_at']);
        });
    }
};
