<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proclamations', function (Blueprint $table) {
            $table->foreignId('publication_id')->nullable()->after('periode_id')->constrained('publications')->nullOnDelete();
        });

        Schema::table('proclamation_lignes', function (Blueprint $table) {
            $table->foreignId('publication_id')->nullable()->after('proclamation_id')->constrained('publications')->nullOnDelete();
        });

        Schema::table('signatures_enseignants', function (Blueprint $table) {
            $table->foreignId('publication_id')->nullable()->after('bulletin_id')->constrained('publications')->nullOnDelete();
        });

        Schema::table('promotions_annuelles', function (Blueprint $table) {
            $table->foreignId('publication_id')->nullable()->after('annee_cible_id')->constrained('publications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promotions_annuelles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publication_id');
        });

        Schema::table('signatures_enseignants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publication_id');
        });

        Schema::table('proclamation_lignes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publication_id');
        });

        Schema::table('proclamations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publication_id');
        });
    }
};
