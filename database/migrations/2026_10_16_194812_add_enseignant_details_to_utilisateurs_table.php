<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->char('sexe', 1)->nullable()->after('prenom');
            $table->date('date_naissance')->nullable()->after('sexe');
            $table->string('lieu_naissance', 150)->nullable()->after('date_naissance');
            $table->string('adresse', 255)->nullable()->after('email');
            $table->string('matricule', 50)->nullable()->after('adresse');
            $table->string('qualification', 150)->nullable()->after('matricule');
            $table->string('specialite', 150)->nullable()->after('qualification');
            $table->date('date_embauche')->nullable()->after('specialite');
        });

        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'matricule'], 'utilisateurs_tenant_matricule_unique');
        });
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->dropUnique('utilisateurs_tenant_matricule_unique');
        });

        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->dropColumn([
                'sexe',
                'date_naissance',
                'lieu_naissance',
                'adresse',
                'matricule',
                'qualification',
                'specialite',
                'date_embauche',
            ]);
        });
    }
};
