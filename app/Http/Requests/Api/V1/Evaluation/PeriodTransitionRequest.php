<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PeriodTransitionRequest extends FormRequest
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
            'motif' => ['required', 'string', 'max:1000'],
        ];
    }
}
