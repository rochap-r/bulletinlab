<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE', 'REFUSE'])->default('ESSAI')->change();
            $table->timestamp('valide_at')->nullable();
            $table->foreignId('valide_par_id')->nullable()->constrained('utilisateurs_plateforme')->nullOnDelete();
            $table->text('motif_refus')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['valide_par_id']);
            $table->dropColumn(['valide_at', 'valide_par_id', 'motif_refus']);
            $table->enum('statut', ['ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE'])->default('ESSAI')->change();
        });
    }
};
