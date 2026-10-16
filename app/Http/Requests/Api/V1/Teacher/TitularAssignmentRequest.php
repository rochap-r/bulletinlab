<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use LogicException;

#[FailOnUnknownFields]
final class TitularAssignmentRequest extends FormRequest
{
    private ?Etablissement $resolvedSchool = null;

    public function authorize(EstablishmentContext $context): bool
    {
        $actor = $this->user();
        if (! $actor instanceof Utilisateur || ! $actor->actif || $actor->etablissement_id === null
            || ! $actor->hasTenantRole(['GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES'])) {
            return false;
        }
        $this->resolvedSchool = $context->current();

        return $this->resolvedSchool->actif
            && $actor->tenant_id === $this->resolvedSchool->tenant_id
            && $actor->etablissement_id === $this->resolvedSchool->id;
    }

    public function school(): Etablissement
    {
        return $this->resolvedSchool ?? throw new LogicException('The titular assignment school was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $actor = $this->user();

        return $actor instanceof Utilisateur ? $actor : throw new LogicException('The titular assignment actor was not resolved.');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'annee_scolaire_id' => ['required', 'integer', 'min:1'],
            ...($this->isMethod('POST') ? [
                'fichier' => ['required', File::types(['xlsx'])->max('5mb'), 'extensions:xlsx'],
            ] : []),
            ...($this->isMethod('PUT') ? [
                'titulariats' => ['required', 'array', 'min:1', 'max:200'],
                'titulariats.*' => ['required', 'array:classe_id,titulaire_id'],
                'titulariats.*.classe_id' => ['required', 'integer', 'min:1', 'distinct'],
                'titulariats.*.titulaire_id' => ['required', 'integer', 'min:1'],
            ] : []),
        ];
    }
}
