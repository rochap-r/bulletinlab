<?php

use App\Support\PhoneNumberNormalizer;
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
        Schema::table('responsables', function (Blueprint $table): void {
            $table->boolean('doit_changer_mot_de_passe')
                ->default(true)
                ->after('mot_de_passe');
        });

        Schema::table('eleves', function (Blueprint $table): void {
            $table->string('telephone_parent_normalise', 16)
                ->nullable()
                ->after('telephone_parent')
                ->index();
        });

        DB::table('eleves')
            ->select(['id', 'telephone_parent'])
            ->whereNotNull('telephone_parent')
            ->orderBy('id')
            ->chunkById(500, function ($students): void {
                foreach ($students as $student) {
                    try {
                        $normalizedPhone = PhoneNumberNormalizer::normalize(
                            (string) $student->telephone_parent,
                        );
                    } catch (InvalidArgumentException) {
                        $normalizedPhone = null;
                    }

                    DB::table('eleves')
                        ->where('id', $student->id)
                        ->update(['telephone_parent_normalise' => $normalizedPhone]);
                }
            });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('responsable_id')
                ->nullable()
                ->after('utilisateur_id')
                ->constrained('responsables')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsable_id');
        });

        Schema::table('eleves', function (Blueprint $table): void {
            $table->dropColumn('telephone_parent_normalise');
        });

        Schema::table('responsables', function (Blueprint $table): void {
            $table->dropColumn('doit_changer_mot_de_passe');
        });
    }
};
