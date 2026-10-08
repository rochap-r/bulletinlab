<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TABLES_WITH_PUBLIC_ID = [
        'etablissements',
        'utilisateurs',
        'classes',
        'eleves',
        'inscriptions',
        'bulletins',
        'notes',
        'attributions_cours',
        'titulaires_classes',
        'modeles_bulletin',
        'proclamations',
    ];

    public function up(): void
    {
        foreach (self::TABLES_WITH_PUBLIC_ID as $table) {
            if (Schema::hasColumn($table, 'public_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->ulid('public_id')->nullable();
                $blueprint->unique('public_id');
            });

            $ids = DB::table($table)->whereNull('public_id')->pluck('id');

            foreach ($ids as $id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update(['public_id' => (string) Str::ulid()]);
            }

            if (DB::connection()->getDriverName() !== 'sqlite') {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->ulid('public_id')->nullable(false)->change();
                });
            }
        }

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('context', 'idx_idempotency_context');
            $table->nullableMorphs('actor', 'idx_idempotency_actor');
            $table->string('command_name', 150);
            $table->string('key', 191);
            $table->string('request_hash', 64);
            $table->nullableMorphs('result', 'idx_idempotency_result');
            $table->unsignedSmallInteger('status')->default(1);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['actor_type', 'actor_id', 'command_name', 'key'], 'idx_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');

        foreach (array_reverse(self::TABLES_WITH_PUBLIC_ID) as $table) {
            if (Schema::hasColumn($table, 'public_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropUnique(['public_id']);
                    $blueprint->dropColumn('public_id');
                });
            }
        }
    }
};
