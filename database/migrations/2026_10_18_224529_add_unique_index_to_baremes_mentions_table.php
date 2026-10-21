<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Contrainte attendue par la cible ON CONFLICT de l'upsert de
     * BulletinLabBaremesSeeder : PostgreSQL refuse l'upsert sans index unique
     * correspondant, et MySQL/SQLite ne signalaient jamais son absence.
     * Le nom reste court (limite de 64 caractères côté MySQL).
     */
    public function up(): void
    {
        Schema::table('baremes_mentions', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'cycle_id', 'system_template_id', 'min_pourcentage', 'max_pourcentage'],
                'baremes_mentions_scope_range_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('baremes_mentions', function (Blueprint $table) {
            $table->dropUnique('baremes_mentions_scope_range_unique');
        });
    }
};
