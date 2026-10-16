<?php

namespace App\Http\Requests\Api\V1\Classroom;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class OrganizeClassesRequest extends FormRequest
{
    public function authorize(EstablishmentContext $establishmentContext, Gate $gate): bool
    {
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
            'annee_scolaire_id' => ['required', 'integer', Rule::exists('annees_scolaires', 'id')],
            'organisations' => ['required', 'array', 'min:1', 'max:30'],
            'organisations.*.cycle_id' => ['required', 'integer', Rule::exists('ref_cycles', 'id')->where('actif', true)],
            'organisations.*.option_id' => ['present', 'nullable', 'integer', Rule::exists('ref_options', 'id')],
            'parallele' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
