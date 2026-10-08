<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('notes')->whereNull('valeur')->exists()) {
            throw new RuntimeException(
                'Existing notes without a value must be classified before adding result statuses.',
            );
        }

        Schema::table('notes', function (Blueprint $table) {
            $table->enum('statut_resultat', [
                'SAISIE',
                'ABSENTE',
                'DISPENSEE',
                'ANNULEE',
            ])->default('SAISIE')->after('periode_id');
            $table->index(
                [
                    'tenant_id',
                    'node_id',
                    'periode_id',
                    'statut_resultat',
                ],
                'notes_tenant_node_period_status_index',
            );
        });

        Schema::table('notes_historiques', function (Blueprint $table) {
            $table->enum('ancien_statut_resultat', [
                'SAISIE',
                'ABSENTE',
                'DISPENSEE',
                'ANNULEE',
            ])->nullable()->after('note_id');
            $table->enum('nouveau_statut_resultat', [
                'SAISIE',
                'ABSENTE',
                'DISPENSEE',
                'ANNULEE',
            ])->nullable()->after('ancien_statut_resultat');
            $table->index(
                ['note_id', 'created_at'],
                'notes_historiques_note_created_index',
            );
        });

        DB::table('notes_historiques')
            ->whereNotNull('ancienne_valeur')
            ->update(['ancien_statut_resultat' => 'SAISIE']);
        DB::table('notes_historiques')
            ->whereNotNull('nouvelle_valeur')
            ->update(['nouveau_statut_resultat' => 'SAISIE']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('notes')
            ->where('statut_resultat', '<>', 'SAISIE')
            ->exists()
            || DB::table('notes_historiques')
                ->where(function ($history): void {
                    $history
                        ->where(function ($oldStatus): void {
                            $oldStatus
                                ->whereNotNull('ancien_statut_resultat')
                                ->where(
                                    'ancien_statut_resultat',
                                    '<>',
                                    'SAISIE',
                                );
                        })
                        ->orWhere(function ($newStatus): void {
                            $newStatus
                                ->whereNotNull('nouveau_statut_resultat')
                                ->where(
                                    'nouveau_statut_resultat',
                                    '<>',
                                    'SAISIE',
                                );
                        });
                })
                ->exists()) {
            throw new RuntimeException(
                'Result statuses must be preserved before rolling back this migration.',
            );
        }

        Schema::table('notes_historiques', function (Blueprint $table) {
            $table->dropIndex('notes_historiques_note_created_index');
            $table->dropColumn([
                'ancien_statut_resultat',
                'nouveau_statut_resultat',
            ]);
        });

        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex('notes_tenant_node_period_status_index');
            $table->dropColumn('statut_resultat');
        });
    }
};
