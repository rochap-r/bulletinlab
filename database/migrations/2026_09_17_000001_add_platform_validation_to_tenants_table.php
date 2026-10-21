<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUT_EXTENDED = ['EN_ATTENTE', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE', 'REFUSE'];

    private const STATUT_ORIGINAL = ['ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->changeStatut(self::STATUT_EXTENDED);

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('valide_at')->nullable();
            $table->foreignId('valide_par_id')->nullable()->constrained('utilisateurs_plateforme')->nullOnDelete();
            $table->text('motif_refus')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['valide_par_id']);
            $table->dropColumn(['valide_at', 'valide_par_id', 'motif_refus']);
        });

        $this->changeStatut(self::STATUT_ORIGINAL);
    }

    /**
     * Restreint (ou étend) les valeurs admises de tenants.statut.
     *
     * La formulation native Laravel (`enum()->change()`) émet sur PostgreSQL un
     * `ALTER COLUMN ... TYPE varchar check (...)`, syntaxe refusée hors CREATE
     * TABLE. Pour ce moteur, la contrainte CHECK est donc reconstruite à la
     * main en reproduisant le résultat attendu : défaut, NOT NULL et liste de
     * valeurs admises. Les autres moteurs suivent la voie native.
     *
     * @param  list<string>  $allowedValues
     */
    private function changeStatut(array $allowedValues): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('tenants', function (Blueprint $table) use ($allowedValues) {
                $table->enum('statut', $allowedValues)->default('ESSAI')->change();
            });

            return;
        }

        $values = implode(', ', array_map(
            fn (string $value): string => "'".str_replace("'", "''", $value)."'",
            $allowedValues,
        ));

        DB::statement('ALTER TABLE "tenants" ALTER COLUMN "statut" SET DEFAULT \'ESSAI\'');
        DB::statement('ALTER TABLE "tenants" ALTER COLUMN "statut" SET NOT NULL');
        DB::statement('ALTER TABLE "tenants" DROP CONSTRAINT IF EXISTS "tenants_statut_check"');
        DB::statement(
            sprintf(
                'ALTER TABLE "tenants" ADD CONSTRAINT "tenants_statut_check" CHECK ("statut" IN (%s))',
                $values,
            ),
        );
    }
};
