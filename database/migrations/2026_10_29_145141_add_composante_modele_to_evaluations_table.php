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
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->string('composante_modele', 40)
                ->nullable()
                ->after('type_evaluation_id');
            $table->decimal('maxima_bulletin', 8, 2)
                ->nullable()
                ->after('maxima');
            $table->unique(
                ['tenant_id', 'classe_id', 'node_id', 'periode_id', 'composante_modele'],
                'evaluations_modele_composante_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropUnique('evaluations_modele_composante_unique');
            $table->dropColumn(['composante_modele', 'maxima_bulletin']);
        });
    }
};
