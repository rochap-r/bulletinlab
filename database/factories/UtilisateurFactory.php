<?php

namespace Database\Factories;

use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Utilisateur>
 */
class UtilisateurFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'nom' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telephone' => '+243'.fake()->unique()->numerify('#########'),
            'mot_de_passe' => bcrypt('password'),
            'actif' => true,
        ];
    }
}
