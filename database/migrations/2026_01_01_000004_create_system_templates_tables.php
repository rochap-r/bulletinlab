<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_layouts_bulletin', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->string('vue_blade', 180)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('system_blocs_speciaux', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('nom', 150);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('system_templates_bulletin', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('nom', 180);
            $table->foreignId('cycle_id')->constrained('ref_cycles')->restrictOnDelete();
            $table->foreignId('degre_id')->nullable()->constrained('ref_degres')->nullOnDelete();
            $table->foreignId('option_id')->nullable()->constrained('ref_options')->nullOnDelete();
            $table->foreignId('niveau_min_id')->nullable()->constrained('ref_niveaux')->nullOnDelete();
            $table->foreignId('niveau_max_id')->nullable()->constrained('ref_niveaux')->nullOnDelete();
            $table->foreignId('layout_id')->constrained('system_layouts_bulletin')->restrictOnDelete();
            $table->enum('type_decoupage', ['TRIMESTRIEL', 'SEMESTRIEL', 'PERSONNALISE']);
            $table->decimal('total_general_maxima', 10, 2)->nullable();
            $table->string('version', 30)->default('2024-2025');
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['cycle_id', 'option_id', 'actif']);
        });

        Schema::create('system_template_blocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->foreignId('bloc_id')->constrained('system_blocs_speciaux')->cascadeOnDelete();
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('actif')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['template_id', 'bloc_id']);
        });

        Schema::create('system_template_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('system_template_nodes')->cascadeOnDelete();
            $table->enum('type_node', ['DOMAINE', 'SOUS_DOMAINE', 'RUBRIQUE', 'GROUPE_MAXIMA', 'MATIERE', 'SOUS_TOTAL']);
            $table->foreignId('matiere_id')->nullable()->constrained('ref_matieres')->nullOnDelete();
            $table->string('libelle', 200);
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('afficher_ligne')->default(true);
            $table->boolean('calculer_sous_total')->default(false);
            $table->boolean('obligatoire')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['template_id', 'parent_id', 'ordre']);
        });

        Schema::create('system_template_periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('system_template_periodes')->nullOnDelete();
            $table->string('code', 60);
            $table->string('nom', 120);
            $table->enum('type_periode', ['PERIODE', 'EXAMEN', 'TRIMESTRE', 'SEMESTRE', 'TOTAL', 'REPECHAGE', 'EXAMEN_ETAT', 'ENAFEP', 'TENASOSP']);
            $table->unsignedInteger('ordre');
            $table->boolean('saisissable')->default(true);
            $table->boolean('calculable')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['template_id', 'code']);
            $table->index(['template_id', 'parent_id', 'ordre']);
        });

        Schema::create('system_template_maxima', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('system_templates_bulletin')->cascadeOnDelete();
            $table->foreignId('node_id')->constrained('system_template_nodes')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('system_template_periodes')->cascadeOnDelete();
            $table->decimal('maxima', 8, 2);
            $table->decimal('coefficient', 6, 2)->default(1);
            $table->timestamps();
            $table->unique(['node_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_template_maxima');
        Schema::dropIfExists('system_template_periodes');
        Schema::dropIfExists('system_template_nodes');
        Schema::dropIfExists('system_template_blocs');
        Schema::dropIfExists('system_templates_bulletin');
        Schema::dropIfExists('system_blocs_speciaux');
        Schema::dropIfExists('system_layouts_bulletin');
    }
};
