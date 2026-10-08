<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rectifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignId('nouvelle_publication_id')->constrained('publications')->cascadeOnDelete();
            $table->text('motif');
            $table->foreignId('rectifiee_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
            $table->unique(['publication_id', 'nouvelle_publication_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rectifications');
    }
};
