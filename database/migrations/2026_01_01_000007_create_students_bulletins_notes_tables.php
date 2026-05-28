<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributions_cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('modele_nodes')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['classe_id','node_id','enseignant_id']);
        });

        Schema::create('eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('num_permanent', 80)->nullable();
            $table->string('nom', 100);
            $table->string('postnom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->enum('sexe', ['M','F'])->nullable();
            $table->string('lieu_naissance', 120)->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('nom_pere', 150)->nullable();
            $table->string('nom_mere', 150)->nullable();
            $table->string('telephone_parent', 30)->nullable();
            $table->text('adresse')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('numero_inscription', 80)->nullable();
            $table->enum('statut', ['ACTIF','ABANDON','TRANSFERE','EXCLU'])->default('ACTIF');
            $table->date('date_inscription')->nullable();
            $table->timestamps();
            $table->unique(['eleve_id','annee_scolaire_id']);
        });

        Schema::create('bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->restrictOnDelete();
            $table->string('id_officiel_document', 100)->nullable();
            $table->decimal('total_obtenu', 10, 2)->nullable();
            $table->decimal('total_maxima', 10, 2)->nullable();
            $table->decimal('pourcentage', 6, 2)->nullable();
            $table->string('mention', 100)->nullable();
            $table->enum('decision_proposee', ['PASSE','DOUBLE','ECHOUE','REPECHAGE','REORIENTE','DIPLOME','AUCUNE'])->nullable();
            $table->unsignedInteger('rang')->nullable();
            $table->unsignedInteger('nombre_eleves_classe')->nullable();
            $table->enum('decision_statut', ['EN_COURS','PASSE','DOUBLE','ECHOUE','REPECHAGE','REORIENTE','DIPLOME'])->default('EN_COURS');
            $table->string('lieu_deliberation', 120)->nullable();
            $table->date('date_deliberation')->nullable();
            $table->text('remarque_finale')->nullable();
            $table->string('pdf_path')->nullable();
            $table->boolean('verrouille')->default(false);
            $table->timestamps();
            $table->unique(['inscription_id','modele_bulletin_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('modele_nodes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->decimal('valeur', 8, 2)->nullable();
            $table->foreignId('saisie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('date_saisie')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->unique(['bulletin_id','node_id','periode_id']);
        });

        Schema::create('notes_historiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('note_id')->constrained('notes')->cascadeOnDelete();
            $table->decimal('ancienne_valeur', 8, 2)->nullable();
            $table->decimal('nouvelle_valeur', 8, 2)->nullable();
            $table->foreignId('modifie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->text('motif')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes_historiques');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('bulletins');
        Schema::dropIfExists('inscriptions');
        Schema::dropIfExists('eleves');
        Schema::dropIfExists('attributions_cours');
    }
};
