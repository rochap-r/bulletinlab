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
        Schema::create('lien_responsable_eleves', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('responsables')->cascadeOnDelete();
            $table->foreignId('eleve_id')->constrained('eleves')->cascadeOnDelete();
            $table->string('type_lien', 20)->default('TUTEUR');
            $table->string('statut', 20)->default('EN_ATTENTE');
            $table->boolean('principal')->default(false);
            $table->boolean('peut_consulter_notes')->default(true);
            $table->boolean('peut_consulter_presences')->default(true);
            $table->boolean('recevoir_notifications')->default(true);
            $table->foreignId('declare_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('verifie_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('verifie_at')->nullable();
            $table->string('methode_verification', 40)->nullable();
            $table->foreignId('revoque_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('revoque_at')->nullable();
            $table->text('motif_revocation')->nullable();
            $table->timestamps();
            $table->unique(['responsable_id', 'eleve_id'], 'lien_responsable_eleve_unique');
            $table->index(['tenant_id', 'responsable_id', 'statut'], 'lien_tenant_responsable_statut_index');
            $table->index(['tenant_id', 'eleve_id', 'statut'], 'lien_tenant_eleve_statut_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lien_responsable_eleves');
    }
};
