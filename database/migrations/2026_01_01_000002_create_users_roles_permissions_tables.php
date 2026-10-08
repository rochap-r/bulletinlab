<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('etablissement_id')->nullable()->constrained('etablissements')->nullOnDelete();
            $table->string('nom', 100);
            $table->string('postnom', 100)->nullable();
            $table->string('prenom', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('telephone', 30);
            $table->string('mot_de_passe');
            $table->string('photo_path')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'telephone']);
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->string('nom', 100);
            $table->boolean('system_role')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('nom', 150);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('utilisateur_roles', function (Blueprint $table) {
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['utilisateur_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utilisateur_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('utilisateurs');
    }
};
