<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'notes_result_status_value_check';

    private const INSERT_TRIGGER = 'notes_result_status_value_insert_guard';

    private const UPDATE_TRIGGER = 'notes_result_status_value_update_guard';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('notes')
            ->whereRaw('('.$this->validResultExpression().') = 0')
            ->exists()) {
            throw new RuntimeException(
                'Existing notes must have a coherent result status and value before adding the database constraint.',
            );
        }

        match (DB::connection()->getDriverName()) {
            'mysql' => $this->addMySqlConstraint(),
            'sqlite' => $this->addSqliteTriggers(),
            default => throw new RuntimeException(
                'Result status constraints are only supported on MySQL and SQLite.',
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
                    'ALTER TABLE `notes` DROP CHECK `%s`',
                    self::CONSTRAINT,
                ),
            ),
            'sqlite' => $this->dropSqliteTriggers(),
            default => throw new RuntimeException(
                'Result status constraints are only supported on MySQL and SQLite.',
            ),
        };
    }

    private function addMySqlConstraint(): void
    {
        DB::statement(
            sprintf(
                'ALTER TABLE `notes` ADD CONSTRAINT `%s` CHECK (%s)',
                self::CONSTRAINT,
                $this->validResultExpression(),
            ),
        );
    }

    private function addSqliteTriggers(): void
    {
        $expression = $this->validResultExpression('NEW.');

        foreach ([
            'INSERT' => self::INSERT_TRIGGER,
            'UPDATE' => self::UPDATE_TRIGGER,
        ] as $event => $trigger) {
            DB::unprepared(
                sprintf(
                    <<<'SQL'
CREATE TRIGGER "%s"
BEFORE %s ON "notes"
FOR EACH ROW
WHEN %s = 0
BEGIN
    SELECT RAISE(ABORT, '%s');
END
SQL,
                    $trigger,
                    $event,
                    $expression,
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

    private function validResultExpression(string $prefix = ''): string
    {
        $status = $prefix.'statut_resultat';
        $value = $prefix.'valeur';

        return "(({$status} = 'SAISIE'"
            ." AND {$value} IS NOT NULL"
            ." AND {$value} >= 0)"
            ." OR ({$status} IN ('ABSENTE', 'DISPENSEE', 'ANNULEE')"
            ." AND {$value} IS NULL))";
    }
};
