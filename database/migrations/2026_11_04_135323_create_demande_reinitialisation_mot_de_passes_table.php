<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_reinitialisation_mot_de_passe', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('traitee_par_id')->nullable()->constrained('utilisateurs_plateforme')->nullOnDelete();
            $table->string('statut', 20)->default('EN_ATTENTE');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('traitee_at')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at'], 'demande_mdp_statut_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_reinitialisation_mot_de_passe');
    }
};
