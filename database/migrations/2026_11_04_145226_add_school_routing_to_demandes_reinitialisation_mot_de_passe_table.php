<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes_reinitialisation_mot_de_passe', function (Blueprint $table): void {
            $table->string('destinataire', 20)->default('PLATEFORME');
            $table->foreignId('traitee_par_utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('demandes_reinitialisation_mot_de_passe', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('traitee_par_utilisateur_id');
            $table->dropColumn('destinataire');
        });
    }
};
