<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('calculation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('system_template_id')->nullable()->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->nullable()->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->string('code', 100);
            $table->enum('type_rule', ['LIGNE_TOTAL','SOUS_TOTAL','TOTAL_PERIODE','TOTAL_GENERAL','POURCENTAGE','RANG','MENTION','DECISION']);
            $table->json('configuration');
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });

        Schema::create('baremes_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('ref_cycles')->nullOnDelete();
            $table->foreignId('system_template_id')->nullable()->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->decimal('min_pourcentage', 6, 2);
            $table->decimal('max_pourcentage', 6, 2);
            $table->string('mention', 100);
            $table->enum('decision_defaut', ['PASSE','DOUBLE','ECHOUE','REPECHAGE','REORIENTE','DIPLOME','AUCUNE'])->default('AUCUNE');
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('proclamations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('modele_periodes')->nullOnDelete();
            $table->enum('type', ['PERIODE','TRIMESTRE','SEMESTRE','ANNUELLE','FINALE'])->default('ANNUELLE');
            $table->enum('statut', ['BROUILLON','GENEREE','VALIDEE','ANNULEE'])->default('BROUILLON');
            $table->date('date_generation')->nullable();
            $table->foreignId('generee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('validee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('validee_at')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('proclamation_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proclamation_id')->constrained('proclamations')->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->unsignedInteger('rang')->nullable();
            $table->decimal('total_obtenu', 10, 2)->nullable();
            $table->decimal('total_maxima', 10, 2)->nullable();
            $table->decimal('pourcentage', 6, 2)->nullable();
            $table->string('mention', 100)->nullable();
            $table->enum('decision', ['EN_COURS','PASSE','DOUBLE','ECHOUE','REPECHAGE','REORIENTE','DIPLOME'])->default('EN_COURS');
            $table->json('details')->nullable();
            $table->timestamps();
            $table->unique(['proclamation_id','bulletin_id'], 'proclamation_ligne_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proclamation_lignes');
        Schema::dropIfExists('proclamations');
        Schema::dropIfExists('baremes_mentions');
        Schema::dropIfExists('calculation_rules');
    }
};
