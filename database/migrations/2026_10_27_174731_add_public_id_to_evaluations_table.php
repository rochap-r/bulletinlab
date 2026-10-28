<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->ulid('public_id')->nullable()->unique()->after('id');
        });

        DB::table('evaluations')
            ->whereNull('public_id')
            ->orderBy('id')
            ->eachById(function (object $evaluation): void {
                DB::table('evaluations')
                    ->where('id', $evaluation->id)
                    ->update(['public_id' => (string) Str::ulid()]);
            });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('evaluations', function (Blueprint $table): void {
                $table->ulid('public_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
