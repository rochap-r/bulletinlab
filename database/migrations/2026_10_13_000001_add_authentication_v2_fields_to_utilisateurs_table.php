<?php

use App\Support\PhoneNumberNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->string('telephone_normalise', 16)->nullable()->after('telephone');
            $table->boolean('doit_changer_mot_de_passe')->default(false)->after('mot_de_passe');
            $table->timestamp('derniere_connexion_at')->nullable()->after('doit_changer_mot_de_passe');
        });

        DB::table('utilisateurs')
            ->select(['id', 'telephone'])
            ->orderBy('id')
            ->chunkById(500, function ($utilisateurs): void {
                foreach ($utilisateurs as $utilisateur) {
                    try {
                        $telephoneNormalise = PhoneNumberNormalizer::normalize($utilisateur->telephone);
                    } catch (InvalidArgumentException $exception) {
                        throw new RuntimeException(
                            "Le téléphone de l'utilisateur {$utilisateur->id} doit être corrigé avant la migration V2.",
                            previous: $exception,
                        );
                    }

                    DB::table('utilisateurs')
                        ->where('id', $utilisateur->id)
                        ->update(['telephone_normalise' => $telephoneNormalise]);
                }
            });

        $conflict = DB::table('utilisateurs')
            ->select(['tenant_id', 'telephone_normalise'])
            ->groupBy('tenant_id', 'telephone_normalise')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($conflict !== null) {
            throw new RuntimeException(
                "Conflit de téléphone normalisé dans le tenant {$conflict->tenant_id}: {$conflict->telephone_normalise}.",
            );
        }

        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->string('telephone_normalise', 16)->nullable(false)->change();
            $table->unique(['tenant_id', 'telephone_normalise'], 'utilisateurs_tenant_telephone_normalise_unique');
            $table->index('telephone_normalise', 'utilisateurs_telephone_normalise_index');
        });
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table): void {
            $table->dropUnique('utilisateurs_tenant_telephone_normalise_unique');
            $table->dropIndex('utilisateurs_telephone_normalise_index');
            $table->dropColumn([
                'telephone_normalise',
                'doit_changer_mot_de_passe',
                'derniere_connexion_at',
            ]);
        });
    }
};
