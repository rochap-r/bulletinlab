<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom_espace', 150);
            $table->string('slug', 100)->unique();
            $table->enum('type_tenant', ['ECOLE', 'GROUPE_ECOLES', 'ENSEIGNANT_INDIVIDUEL'])->default('ECOLE');
            $table->enum('statut', ['ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE'])->default('ESSAI');
            $table->timestamps();
        });

        Schema::create('etablissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('nom', 180);
            $table->string('code_ministere', 80)->nullable();
            $table->string('province_educationnelle', 150)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('ville', 100)->nullable();
            $table->string('commune_territoire', 100)->nullable();
            $table->text('adresse')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('sceau_path')->nullable();
            $table->string('signature_directeur_path')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'actif']);
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 100);
            $table->decimal('prix_mensuel', 10, 2)->default(0);
            $table->decimal('prix_annuel', 10, 2)->default(0);
            $table->unsignedInteger('limite_eleves')->nullable();
            $table->unsignedInteger('limite_utilisateurs')->nullable();
            $table->unsignedInteger('limite_etablissements')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->enum('statut', ['ESSAI', 'ACTIF', 'PAST_DUE', 'SUSPENDU', 'ANNULE'])->default('ESSAI');
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'statut']);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('abonnement_id')->nullable()->constrained('abonnements')->nullOnDelete();
            $table->decimal('montant', 10, 2);
            $table->string('devise', 10)->default('USD');
            $table->string('methode', 50)->nullable();
            $table->string('reference_externe', 150)->nullable();
            $table->enum('statut', ['EN_ATTENTE', 'SUCCES', 'ECHEC', 'ANNULE'])->default('EN_ATTENTE');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('abonnements');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('etablissements');
        Schema::dropIfExists('tenants');
    }
};
