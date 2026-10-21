<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'note_histories_result_status_value_check';

    private const INSERT_TRIGGER = 'note_histories_result_status_value_insert_guard';

    private const UPDATE_TRIGGER = 'note_histories_result_status_value_update_guard';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('notes_historiques')
            ->whereRaw($this->invalidHistoryExpression())
            ->exists()) {
            throw new RuntimeException(
                'Existing note histories must have coherent result statuses and values before adding the database constraint.',
            );
        }

        match (DB::connection()->getDriverName()) {
            'mysql' => $this->addMySqlConstraint(),
            'pgsql' => $this->addPostgresConstraint(),
            'sqlite' => $this->addSqliteTriggers(),
            default => throw new RuntimeException(
                'Note history result constraints are only supported on MySQL, PostgreSQL and SQLite.',
            ),
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        match (DB::connection()->getDriverName()) {
            'mysql' => DB::statement(
                sprintf(
                    'ALTER TABLE `notes_historiques` DROP CHECK `%s`',
                    self::CONSTRAINT,
                ),
            ),
            'pgsql' => DB::statement(
                sprintf(
                    'ALTER TABLE "notes_historiques" DROP CONSTRAINT "%s"',
                    self::CONSTRAINT,
                ),
            ),
            'sqlite' => $this->dropSqliteTriggers(),
            default => throw new RuntimeException(
                'Note history result constraints are only supported on MySQL, PostgreSQL and SQLite.',
            ),
        };
    }

    private function addMySqlConstraint(): void
    {
        DB::statement(
            sprintf(
                'ALTER TABLE `notes_historiques` ADD CONSTRAINT `%s` CHECK (%s AND %s)',
                self::CONSTRAINT,
                $this->validResultExpression(
                    'ancien_statut_resultat',
                    'ancienne_valeur',
                ),
                $this->validResultExpression(
                    'nouveau_statut_resultat',
                    'nouvelle_valeur',
                ),
            ),
        );
    }

    private function addPostgresConstraint(): void
    {
        DB::statement(
            sprintf(
                'ALTER TABLE "notes_historiques" ADD CONSTRAINT "%s" CHECK (%s AND %s)',
                self::CONSTRAINT,
                $this->validResultExpression(
                    'ancien_statut_resultat',
                    'ancienne_valeur',
                ),
                $this->validResultExpression(
                    'nouveau_statut_resultat',
                    'nouvelle_valeur',
                ),
            ),
        );
    }

    private function addSqliteTriggers(): void
    {
        $invalidExpression = $this->invalidHistoryExpression('NEW.');

        foreach ([
            'INSERT' => self::INSERT_TRIGGER,
            'UPDATE' => self::UPDATE_TRIGGER,
        ] as $event => $trigger) {
            DB::unprepared(
                sprintf(
                    <<<'SQL'
CREATE TRIGGER "%s"
BEFORE %s ON "notes_historiques"
FOR EACH ROW
WHEN %s
BEGIN
    SELECT RAISE(ABORT, '%s');
END
SQL,
                    $trigger,
                    $event,
                    $invalidExpression,
                    self::CONSTRAINT,
                ),
            );
        }
    }

    private function dropSqliteTriggers(): void
    {
        DB::unprepared(
            sprintf(
                'DROP TRIGGER IF EXISTS "%s"',
                self::INSERT_TRIGGER,
            ),
        );
        DB::unprepared(
            sprintf(
                'DROP TRIGGER IF EXISTS "%s"',
                self::UPDATE_TRIGGER,
            ),
        );
    }

    private function invalidHistoryExpression(string $prefix = ''): string
    {
        return $this->validResultExpression(
            'ancien_statut_resultat',
            'ancienne_valeur',
            $prefix,
        ).' = '.$this->falseLiteral().' OR '
            .$this->validResultExpression(
                'nouveau_statut_resultat',
                'nouvelle_valeur',
                $prefix,
            ).' = '.$this->falseLiteral();
    }

    private function validResultExpression(
        string $statusColumn,
        string $valueColumn,
        string $prefix = '',
    ): string {
        $status = $prefix.$statusColumn;
        $value = $prefix.$valueColumn;

        return "COALESCE((({$status} IS NULL AND {$value} IS NULL)"
            ." OR ({$status} = 'SAISIE'"
            ." AND {$value} IS NOT NULL"
            ." AND {$value} >= 0)"
            ." OR ({$status} IN ('ABSENTE', 'DISPENSEE', 'ANNULEE')"
            ." AND {$value} IS NULL)), ".$this->falseLiteral().')';
    }

    /**
     * Littéral « faux » du moteur courant : false (booléen natif) sur
     * PostgreSQL, 0 sur MySQL et SQLite.
     */
    private function falseLiteral(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'false' : '0';
    }
};
