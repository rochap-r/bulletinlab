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
        Schema::create('resultats_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('statut_resultat', 20)->default('SAISIE');
            $table->decimal('valeur', 8, 2)->nullable();
            $table->text('commentaire_parent')->nullable();
            $table->text('commentaire_interne')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('date_saisie')->nullable();
            $table->integer('lock_version')->default(1);
            $table->timestamps();

            $table->unique(['evaluation_id', 'inscription_id'], 'resultat_evaluation_unique');
            $table->index(['tenant_id', 'evaluation_id'], 'resultat_evaluation_tenant_evaluation_index');
            $table->index(['tenant_id', 'inscription_id'], 'resultat_evaluation_tenant_inscription_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resultats_evaluations');
    }
};
