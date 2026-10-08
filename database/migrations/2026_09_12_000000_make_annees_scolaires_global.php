<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropUnique('annees_scolaires_tenant_id_libelle_unique');
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });

        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->unique(['calendrier_scolaire_id', 'libelle']);
        });
    }

    public function down(): void
    {
        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->dropUnique('annees_scolaires_calendrier_scolaire_id_libelle_unique');
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });

        Schema::table('annees_scolaires', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unique(['tenant_id', 'libelle']);
        });
    }
};
