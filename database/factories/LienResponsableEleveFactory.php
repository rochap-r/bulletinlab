<?php

namespace Database\Factories;

use App\Enums\StatutLienResponsable;
use App\Enums\TypeLienResponsable;
use App\Models\Eleve;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LienResponsableEleve>
 */
class LienResponsableEleveFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'responsable_id' => Responsable::factory(),
            'type_lien' => fake()->randomElement(TypeLienResponsable::cases()),
            'statut' => StatutLienResponsable::EnAttente,
            'principal' => false,
            'peut_consulter_notes' => true,
            'peut_consulter_presences' => true,
            'recevoir_notifications' => true,
        ];
    }

    public function forEleve(Eleve $eleve): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $eleve->tenant_id,
            'eleve_id' => $eleve->getKey(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'statut' => StatutLienResponsable::Verifie,
            'verifie_at' => now(),
            'methode_verification' => 'VALIDATION_ECOLE',
        ]);
    }
}
