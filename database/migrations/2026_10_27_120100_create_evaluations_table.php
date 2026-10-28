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
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('attribution_cours_id')->constrained('attributions_cours')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('modele_nodes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations')->cascadeOnDelete();
            $table->string('libelle', 200);
            $table->decimal('maxima', 8, 2);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->date('date_evaluation');
            $table->string('statut', 20)->default('BROUILLON');
            $table->timestamp('visible_parents_at')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->index(['tenant_id', 'classe_id', 'periode_id'], 'evaluations_tenant_classe_periode_index');
            $table->index(['tenant_id', 'attribution_cours_id'], 'evaluations_tenant_attribution_index');
            $table->index(['tenant_id', 'node_id', 'periode_id'], 'evaluations_tenant_node_periode_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
