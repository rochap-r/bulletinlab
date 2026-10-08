<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('nom', 120);
            $table->unsignedInteger('ordre')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('ref_degres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('ref_cycles')->cascadeOnDelete();
            $table->string('code', 50)->unique();
            $table->string('nom', 120);
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });

        Schema::create('ref_niveaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('ref_cycles')->cascadeOnDelete();
            $table->foreignId('degre_id')->nullable()->constrained('ref_degres')->nullOnDelete();
            $table->string('code', 50)->unique();
            $table->string('nom', 120);
            $table->unsignedTinyInteger('numero');
            $table->unsignedInteger('ordre')->default(1);
            $table->timestamps();
        });

        Schema::create('ref_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->nullable()->constrained('ref_cycles')->nullOnDelete();
            $table->string('code', 80)->unique();
            $table->string('nom', 150);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('ref_matieres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('nom', 180);
            $table->string('nom_court', 80)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_matieres');
        Schema::dropIfExists('ref_options');
        Schema::dropIfExists('ref_niveaux');
        Schema::dropIfExists('ref_degres');
        Schema::dropIfExists('ref_cycles');
    }
};
