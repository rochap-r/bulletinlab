<?php

namespace Database\Factories;

use App\Models\Responsable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Responsable>
 */
class ResponsableFactory extends Factory
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
            'postnom' => fake()->optional()->lastName(),
            'prenom' => fake()->firstName(),
            'telephone' => '+243'.fake()->unique()->numerify('#########'),
            'email' => fake()->boolean(70)
                ? fake()->unique()->safeEmail()
                : null,
            'mot_de_passe' => null,
            'telephone_verifie_at' => null,
            'actif' => true,
        ];
    }

    public function withDefaultPassword(): static
    {
        return $this->state(fn (): array => [
            'mot_de_passe' => bcrypt((string) config('responsables.default_password', '123456')),
            'doit_changer_mot_de_passe' => true,
        ]);
    }
}
