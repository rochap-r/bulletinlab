<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendrier_periodes', function (Blueprint $table) {
            $table->string('cycle_scolaire', 40)->default('TOUS')->after('type_periode')->index();
        });

        Schema::create('calendrier_cycle_parametres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendrier_scolaire_id')->constrained('calendriers_scolaires')->cascadeOnDelete();
            $table->string('cycle_scolaire', 40);
            $table->string('mode_decoupage', 30);
            $table->unsignedSmallInteger('jours_ouvrables_attendus');
            $table->unsignedSmallInteger('heures_minimales')->default(900);
            $table->json('jours_classe');
            $table->timestamps();
            $table->unique(['calendrier_scolaire_id', 'cycle_scolaire'], 'calendar_cycle_unique');
        });

        Schema::create('calendrier_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendrier_scolaire_id')->constrained('calendriers_scolaires')->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('nom', 180);
            $table->string('type_evenement', 40)->index();
            $table->string('cycle_scolaire', 40)->default('TOUS')->index();
            $table->date('date_debut')->index();
            $table->date('date_fin');
            $table->json('audiences');
            $table->string('niveau_alerte', 20)->default('INFORMATION');
            $table->unsignedSmallInteger('delai_alerte_jours')->default(7);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['calendrier_scolaire_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendrier_evenements');
        Schema::dropIfExists('calendrier_cycle_parametres');

        Schema::table('calendrier_periodes', function (Blueprint $table) {
            $table->dropColumn('cycle_scolaire');
        });
    }
};
