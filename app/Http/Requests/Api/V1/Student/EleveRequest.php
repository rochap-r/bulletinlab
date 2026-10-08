<?php

namespace App\Http\Requests\Api\V1\Student;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
class EleveRequest extends FormRequest
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'num_permanent' => ['sometimes', 'nullable', 'string', 'max:80'],
            'nom' => [$required, 'string', 'max:100'],
            'postnom' => ['sometimes', 'nullable', 'string', 'max:100'],
            'prenom' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sexe' => ['sometimes', 'nullable', Rule::in(['M', 'F'])],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:120'],
            'date_naissance' => ['sometimes', 'nullable', 'date'],
            'nom_pere' => ['sometimes', 'nullable', 'string', 'max:150'],
            'nom_mere' => ['sometimes', 'nullable', 'string', 'max:150'],
            'telephone_parent' => ['sometimes', 'nullable', 'string', 'max:30'],
            'adresse' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
