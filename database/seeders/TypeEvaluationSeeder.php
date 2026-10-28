<?php

namespace Database\Seeders;

use App\Models\TypeEvaluation;
use Illuminate\Database\Seeder;
use LogicException;

class TypeEvaluationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Le jeu de types d\'évaluations est réservé aux environnements local et testing.');
        }

        $types = [
            ['code' => 'INTERROGATION', 'nom' => 'Interrogation', 'description' => 'Interrogation orale ou écrite'],
            ['code' => 'EXERCICE', 'nom' => 'Exercice', 'description' => 'Travail réalisé en classe'],
            ['code' => 'DEVOIR', 'nom' => 'Devoir', 'description' => 'Travail à la maison'],
            ['code' => 'CAHIER', 'nom' => 'Cahier', 'description' => 'Cotation du cahier et des exercices faits'],
            ['code' => 'AUTRE', 'nom' => 'Autre', 'description' => 'Autre composante d\'évaluation'],
        ];

        foreach ($types as $type) {
            TypeEvaluation::query()->updateOrCreate(
                ['code' => $type['code']],
                $type,
            );
        }
    }
}
