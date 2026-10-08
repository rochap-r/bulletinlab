<?php

namespace App\Http\Requests\Api\V1\SchoolYear;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class OpenOuvertureAnnuelleRequest extends FormRequest
{
    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $gate->forUser($user)->allows(
                'manageAcademicStructure',
                $establishmentContext->current(),
            );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'annee_scolaire_id' => [
                'sometimes',
                'integer',
                Rule::exists('annees_scolaires', 'id')->where('active', true),
            ],
            'cycles' => ['sometimes', 'array'],
            'cycles.*' => ['string', 'max:80', Rule::exists('ref_cycles', 'code')],
        ];
    }
}
