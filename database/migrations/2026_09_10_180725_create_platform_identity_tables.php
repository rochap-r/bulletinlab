<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs_plateforme', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('nom', 100);
            $table->string('postnom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->string('mot_de_passe');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('roles_plateforme', function (Blueprint $table) {
            $table->string('code', 80)->primary();
            $table->string('nom', 150);
            $table->timestamps();
        });

        Schema::create('utilisateur_plateforme_roles', function (Blueprint $table) {
            $table->foreignId('utilisateur_plateforme_id')->constrained('utilisateurs_plateforme')->cascadeOnDelete();
            $table->string('role_code', 80);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['utilisateur_plateforme_id', 'role_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateur_plateforme_roles');
        Schema::dropIfExists('roles_plateforme');
        Schema::dropIfExists('utilisateurs_plateforme');
    }
};
