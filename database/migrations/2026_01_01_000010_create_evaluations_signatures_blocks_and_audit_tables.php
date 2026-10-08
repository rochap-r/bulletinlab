<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures_enseignants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('attribution_cours_id')->constrained('attributions_cours')->cascadeOnDelete();
            $table->string('signature_path')->nullable();
            $table->boolean('signe')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->unique(['bulletin_id', 'attribution_cours_id'], 'signature_unique_bulletin_attribution');
        });

        Schema::create('appreciations_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('modele_periodes')->nullOnDelete();
            $table->foreignId('auteur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->enum('type_appreciation', ['TITULAIRE', 'DIRECTION', 'FINAL']);
            $table->text('contenu');
            $table->timestamps();
        });

        Schema::create('bulletin_bloc_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('bloc_id')->constrained('system_blocs_speciaux')->restrictOnDelete();
            $table->json('data');
            $table->timestamps();

            $table->unique(['bulletin_id', 'bloc_id']);
        });

        Schema::create('verrouillages_periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->boolean('verrouille')->default(false);
            $table->foreignId('verrouille_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('date_verrouillage')->nullable();
            $table->timestamps();

            $table->unique(['classe_id', 'periode_id']);
        });

        Schema::create('imports_fichiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->enum('type_import', ['ELEVES', 'NOTES', 'ENSEIGNANTS', 'ATTRIBUTIONS']);
            $table->string('fichier_path')->nullable();
            $table->enum('statut', ['EN_ATTENTE', 'TRAITE', 'ECHEC', 'PARTIEL'])->default('EN_ATTENTE');
            $table->json('rapport')->nullable();
            $table->timestamps();
        });

        Schema::create('promotions_annuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_source_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->foreignId('annee_cible_id')->constrained('annees_scolaires')->restrictOnDelete();
            $table->enum('statut', ['BROUILLON', 'VALIDE', 'ANNULE'])->default('BROUILLON');
            $table->json('configuration')->nullable();
            $table->timestamps();
        });

        Schema::create('promotions_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions_annuelles')->cascadeOnDelete();
            $table->foreignId('inscription_source_id')->constrained('inscriptions')->restrictOnDelete();
            $table->foreignId('classe_cible_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->enum('decision', ['PASSE', 'DOUBLE', 'TRANSFERE', 'ABANDON']);
            $table->foreignId('inscription_cible_id')->nullable()->constrained('inscriptions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->string('action', 120);
            $table->string('table_cible', 120)->nullable();
            $table->unsignedBigInteger('ligne_cible_id')->nullable();
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->string('ip_address', 60)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('promotions_eleves');
        Schema::dropIfExists('promotions_annuelles');
        Schema::dropIfExists('imports_fichiers');
        Schema::dropIfExists('verrouillages_periodes');
        Schema::dropIfExists('bulletin_bloc_values');
        Schema::dropIfExists('appreciations_bulletins');
        Schema::dropIfExists('signatures_enseignants');
        Schema::dropIfExists('types_evaluations_globales');
    }
};
