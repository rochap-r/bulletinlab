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
        Schema::table('notes', function (Blueprint $table): void {
            $table->string('origine', 20)->default('DIRECTE')->after('commentaire');
            $table->string('calcul_version', 40)->nullable()->after('origine');
            $table->char('calcul_hash', 64)->nullable()->after('calcul_version');
            $table->timestamp('calculee_at')->nullable()->after('calcul_hash');
            $table->index(['origine'], 'notes_origine_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table): void {
            $table->dropIndex(['notes_origine_index']);
            $table->dropColumn([
                'origine',
                'calcul_version',
                'calcul_hash',
                'calculee_at',
            ]);
        });
    }
};
