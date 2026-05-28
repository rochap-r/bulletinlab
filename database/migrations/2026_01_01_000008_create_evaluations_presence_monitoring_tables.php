<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('types_evaluations_globales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('nom', 120);
            $table->timestamps();
        });

        Schema::create('evaluations_globales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('type_evaluation_id')->constrained('types_evaluations_globales')->restrictOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('modele_periodes')->nullOnDelete();
            $table->string('valeur', 20)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();
            $table->unique(['bulletin_id','type_evaluation_id','periode_id'], 'eval_unique_bulletin_type_periode');
        });

        Schema::create('baremes_appreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('code', 30);
            $table->string('libelle', 120);
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id','type','code']);
        });

        Schema::create('presences_eleves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->foreignId('periode_id')->nullable()->constrained('modele_periodes')->nullOnDelete();
            $table->date('date_presence');
            $table->enum('moment', ['JOURNEE','MATIN','APRES_MIDI'])->default('JOURNEE');
            $table->enum('statut', ['PRESENT','ABSENT','RETARD','JUSTIFIE'])->default('PRESENT');
            $table->text('observation')->nullable();
            $table->foreignId('saisi_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['inscription_id','date_presence','moment'], 'unique_presence_eleve_jour_moment');
        });

        Schema::create('suivis_encodage_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('modele_nodes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->foreignId('enseignant_id')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->unsignedInteger('nombre_eleves')->default(0);
            $table->unsignedInteger('nombre_notes_saisies')->default(0);
            $table->decimal('pourcentage_avancement', 5, 2)->default(0);
            $table->enum('statut', ['NON_COMMENCE','EN_COURS','TERMINE','EN_RETARD','VERROUILLE'])->default('NON_COMMENCE');
            $table->timestamp('derniere_saisie_at')->nullable();
            $table->timestamps();
            $table->unique(['classe_id','node_id','periode_id'], 'suivi_encodage_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suivis_encodage_notes');
        Schema::dropIfExists('presences_eleves');
        Schema::dropIfExists('baremes_appreciations');
        Schema::dropIfExists('evaluations_globales');
        Schema::dropIfExists('types_evaluations_globales');
    }
};
