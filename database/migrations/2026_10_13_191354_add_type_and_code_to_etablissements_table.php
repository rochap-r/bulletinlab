<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->enum('type_etablissement', ['PRIMAIRE', 'SECONDAIRE', 'MATERNELLE', 'MIXTE'])->default('MIXTE')->after('nom');
            $table->string('code_interne', 80)->nullable()->after('code_ministere');
            $table->unique(['tenant_id', 'code_interne'], 'etablissements_tenant_code_interne_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('etablissements', function (Blueprint $table) {
            $table->dropUnique('etablissements_tenant_code_interne_unique');
            $table->dropColumn(['type_etablissement', 'code_interne']);
        });
    }
};
