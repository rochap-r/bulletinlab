<?php

namespace Database\Factories;

use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'etablissement_id' => 1,
            'email' => fake()->unique()->safeEmail(),
            'role_id' => 1,
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'expire_at' => now()->addDays(7),
            'created_by' => null,
        ];
    }
}
