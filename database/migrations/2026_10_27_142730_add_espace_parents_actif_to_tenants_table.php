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
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('espace_parents_actif')->default(true)->after('statut');
            $table->index('espace_parents_actif', 'tenants_espace_parents_actif_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropIndex('tenants_espace_parents_actif_index');
            $table->dropColumn('espace_parents_actif');
        });
    }
};
