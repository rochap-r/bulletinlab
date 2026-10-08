<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('ref_cycles')->restrictOnDelete();
            $table->foreignId('degre_id')->nullable()->constrained('ref_degres')->nullOnDelete();
            $table->foreignId('niveau_id')->constrained('ref_niveaux')->restrictOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('ref_options')->nullOnDelete();
            $table->foreignId('system_template_id')->constrained('system_templates_bulletin')->restrictOnDelete();
            $table->string('parallele', 20)->nullable(); // A, B, C...
            $table->string('nom', 120);
            $table->string('code', 80)->nullable();
            $table->foreignId('titulaire_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'etablissement_id', 'annee_scolaire_id', 'nom'], 'classes_unique_nom_annee');
        });

        Schema::create('titulaires_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('modeles_bulletin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('system_template_id')->constrained('system_templates_bulletin')->restrictOnDelete();
            $table->foreignId('layout_id')->constrained('system_layouts_bulletin')->restrictOnDelete();
            $table->string('nom', 180);
            $table->enum('type_decoupage', ['TRIMESTRIEL', 'SEMESTRIEL', 'PERSONNALISE']);
            $table->decimal('total_general_maxima', 10, 2)->nullable();
            $table->boolean('verrouille')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['classe_id', 'annee_scolaire_id']);
        });

        Schema::create('modele_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('system_node_id')->nullable()->constrained('system_template_nodes')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('modele_nodes')->cascadeOnDelete();
            $table->enum('type_node', ['DOMAINE', 'SOUS_DOMAINE', 'RUBRIQUE', 'GROUPE_MAXIMA', 'MATIERE', 'SOUS_TOTAL']);
            $table->foreignId('matiere_id')->nullable()->constrained('ref_matieres')->nullOnDelete();
            $table->string('libelle', 200);
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('afficher_ligne')->default(true);
            $table->boolean('calculer_sous_total')->default(false);
            $table->boolean('obligatoire')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('modele_periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('system_periode_id')->nullable()->constrained('system_template_periodes')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('modele_periodes')->nullOnDelete();
            $table->string('code', 60);
            $table->string('nom', 120);
            $table->enum('type_periode', ['PERIODE', 'EXAMEN', 'TRIMESTRE', 'SEMESTRE', 'TOTAL', 'REPECHAGE', 'EXAMEN_ETAT', 'ENAFEP', 'TENASOSP']);
            $table->unsignedInteger('ordre');
            $table->boolean('saisissable')->default(true);
            $table->boolean('calculable')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['modele_bulletin_id', 'code']);
        });

        Schema::create('modele_maxima', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('modele_nodes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->decimal('maxima', 8, 2);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->timestamps();
            $table->unique(['node_id', 'periode_id']);
        });

        Schema::create('modele_blocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('bloc_id')->constrained('system_blocs_speciaux')->restrictOnDelete();
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('actif')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['modele_bulletin_id', 'bloc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modele_blocs');
        Schema::dropIfExists('modele_maxima');
        Schema::dropIfExists('modele_periodes');
        Schema::dropIfExists('modele_nodes');
        Schema::dropIfExists('modeles_bulletin');
        Schema::dropIfExists('titulaires_classes');
        Schema::dropIfExists('classes');
    }
};
