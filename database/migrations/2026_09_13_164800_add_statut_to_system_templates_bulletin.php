<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_templates_bulletin', function (Blueprint $table) {
            $table->enum('statut', ['BROUILLON', 'VALIDE', 'PUBLIE', 'DEPRECIE'])
                ->default('BROUILLON')
                ->after('actif');
            $table->timestamp('publie_a')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('system_templates_bulletin', function (Blueprint $table) {
            $table->dropColumn(['statut', 'publie_a']);
        });
    }
};
