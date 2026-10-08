<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendriers_scolaires', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('libelle', 150);
            $table->string('pays', 80)->default('RDC');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('officiel')->default(true);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('calendrier_periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendrier_scolaire_id')->constrained('calendriers_scolaires')->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('nom', 150);
            $table->enum('type_periode', ['PERIODE', 'EXAMEN', 'SEMESTRE', 'TRIMESTRE', 'CONGE', 'DELIBERATION', 'PROCLAMATION']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('saisie_notes_ouverte')->default(false);
            $table->boolean('periode_active')->default(false);
            $table->timestamps();
            $table->unique(['calendrier_scolaire_id', 'code']);
        });

        Schema::create('annees_scolaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('calendrier_scolaire_id')->nullable()->constrained('calendriers_scolaires')->nullOnDelete();
            $table->string('libelle', 20);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('active')->default(false);
            $table->boolean('cloturee')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'libelle']);
        });

        Schema::create('annee_periode_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('calendrier_periode_id')->constrained('calendrier_periodes')->cascadeOnDelete();
            $table->string('code_periode_template', 60);
            $table->boolean('saisie_ouverte')->default(false);
            $table->boolean('verrouillee')->default(false);
            $table->timestamps();
            $table->unique(['annee_scolaire_id', 'code_periode_template'], 'annee_template_period_unique');
        });

        Schema::create('tenant_cycles_actifs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('ref_cycles')->restrictOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'annee_scolaire_id', 'cycle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_cycles_actifs');
        Schema::dropIfExists('annee_periode_mappings');
        Schema::dropIfExists('annees_scolaires');
        Schema::dropIfExists('calendrier_periodes');
        Schema::dropIfExists('calendriers_scolaires');
    }
};
