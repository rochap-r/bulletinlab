<?php

namespace Database\Seeders;

use App\Models\Responsable;
use Illuminate\Database\Seeder;
use LogicException;

class ResponsableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Le jeu démo des responsables est réservé aux environnements local et testing.');
        }

        Responsable::query()
            ->where('telephone_normalise', '+243000000011')
            ->first()
            ?? Responsable::factory()->withDefaultPassword()->create([
                'nom' => 'Kabeya',
                'prenom' => 'Marie',
                'telephone' => '+243000000011',
                'email' => 'parent.demo@bulletinlab.test',
            ]);
    }
}
