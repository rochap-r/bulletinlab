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
        Schema::table('eleves', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id'], 'eleves_tenant_id_id_unique');
        });

        Schema::table('lien_responsable_eleves', function (Blueprint $table): void {
            $table->foreign(
                ['tenant_id', 'eleve_id'],
                'lien_responsable_eleve_tenant_eleve_foreign',
            )->references(['tenant_id', 'id'])
                ->on('eleves')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lien_responsable_eleves', function (Blueprint $table): void {
            $table->dropForeign('lien_responsable_eleve_tenant_eleve_foreign');
        });

        Schema::table('eleves', function (Blueprint $table): void {
            $table->dropUnique('eleves_tenant_id_id_unique');
        });
    }
};
