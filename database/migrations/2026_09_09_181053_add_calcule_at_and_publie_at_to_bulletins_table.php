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
        Schema::table('bulletins', function (Blueprint $table) {
            $table->timestamp('calcule_at')->nullable()->after('verrouille');
            $table->timestamp('publie_at')->nullable()->after('calcule_at');
        });
    }

    public function down(): void
    {
        Schema::table('bulletins', function (Blueprint $table) {
            $table->dropColumn(['calcule_at', 'publie_at']);
        });
    }
};
