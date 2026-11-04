<?php

namespace Database\Factories;

use App\Models\DemandeReinitialisationMotDePasse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandeReinitialisationMotDePasse>
 */
class DemandeReinitialisationMotDePasseFactory extends Factory
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
            'utilisateur_id' => 1,
            'statut' => 'EN_ATTENTE',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
