<?php

namespace Database\Factories;

use App\Models\UtilisateurPlateforme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilisateurPlateforme>
 */
class UtilisateurPlateformeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'postnom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'mot_de_passe' => bcrypt('password'),
            'actif' => true,
        ];
    }
}
