<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('periode_id')->constrained('modele_periodes')->cascadeOnDelete();
            $table->foreignId('resultat_bulletin_id')->constrained('resultats_bulletins')->cascadeOnDelete();
            $table->char('checksum', 64);
            $table->string('engine_version', 30);
            $table->enum('statut', ['PUBLIEE', 'RECTIFIEE'])->default('PUBLIEE');
            $table->foreignId('publiee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('publiee_at')->nullable();
            $table->timestamps();
            $table->unique('resultat_bulletin_id');
            $table->index(['tenant_id', 'bulletin_id', 'periode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
