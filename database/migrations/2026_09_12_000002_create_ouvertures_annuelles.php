<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ouvertures_annuelles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('annee_scolaire_id')->constrained('annees_scolaires')->cascadeOnDelete();
            $table->string('statut', 20)->default('OUVERTE');
            $table->timestamp('activee_at')->nullable();
            $table->foreignId('activee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('cloturee_at')->nullable();
            $table->foreignId('cloturee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();

            $table->unique(['etablissement_id', 'annee_scolaire_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ouvertures_annuelles');
    }
};
