<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultats_bulletins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->foreignId('modele_bulletin_id')->constrained('modeles_bulletin')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('engine_version', 30);
            $table->char('configuration_hash', 64);
            $table->char('input_hash', 64);
            $table->char('result_hash', 64);
            $table->decimal('total_obtenu', 10, 2)->nullable();
            $table->decimal('total_maxima', 10, 2)->nullable();
            $table->decimal('pourcentage', 6, 2)->nullable();
            $table->string('mention', 100)->nullable();
            $table->enum(
                'decision',
                ['EN_COURS', 'PASSE', 'DOUBLE', 'ECHOUE', 'REPECHAGE', 'REORIENTE', 'DIPLOME'],
            )->nullable();
            $table->unsignedInteger('rang')->nullable();
            $table->unsignedInteger('nombre_eleves_classe')->nullable();
            $table->json('payload');
            $table->timestamps();
            $table->unique(['bulletin_id', 'periode_id', 'version']);
            $table->index(['tenant_id', 'bulletin_id', 'periode_id'], 'resultats_bulletins_tenant_bulletin_periode_index');
            $table->index(['result_hash', 'input_hash', 'configuration_hash'], 'resultats_bulletins_hashes_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultats_bulletins');
    }
};
