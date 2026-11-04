<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paneles de antibiograma (D-0021): un panel vale para uno o más grupos de germen y para
 * una o más muestras (ninguna = todas), y puede ser un panel VITEK. Los paneles existentes
 * conservan su grupo y quedan para todas las muestras, como hasta ahora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ast_panels', function (Blueprint $table) {
            $table->json('organism_groups')->nullable()->after('name');
            $table->boolean('is_vitek')->default(false)->after('organism_groups');
            $table->unsignedInteger('lock_version')->default(0);
        });

        DB::table('ast_panels')->update(['organism_groups' => DB::raw('JSON_ARRAY(organism_group)')]);

        Schema::table('ast_panels', function (Blueprint $table) {
            $table->dropIndex(['organism_group']);
            $table->dropColumn('organism_group');
        });

        Schema::create('ast_panel_sample_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ast_panel_id')->constrained()->restrictOnDelete();
            $table->foreignId('sample_type_id')->constrained()->restrictOnDelete();
            $table->unique(['ast_panel_id', 'sample_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ast_panel_sample_types');

        Schema::table('ast_panels', function (Blueprint $table) {
            $table->string('organism_group', 40)->nullable()->after('name')->index();
        });

        DB::table('ast_panels')->update(['organism_group' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(organism_groups, '$[0]'))")]);

        Schema::table('ast_panels', function (Blueprint $table) {
            $table->dropColumn(['organism_groups', 'is_vitek', 'lock_version']);
        });
    }
};
