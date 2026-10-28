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
        Schema::create('responsables', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('nom', 100);
            $table->string('postnom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->string('telephone', 30);
            $table->string('telephone_normalise', 16)->unique();
            $table->string('email', 150)->nullable();
            $table->string('mot_de_passe')->nullable();
            $table->timestamp('telephone_verifie_at')->nullable();
            $table->timestamp('derniere_connexion_at')->nullable();
            $table->boolean('actif')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('responsables');
    }
};
