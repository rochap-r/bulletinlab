<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\StatutEvaluation;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class StoreEvaluationRequest extends FormRequest
{
    public function authorize(Gate $gate, EstablishmentContext $context): bool
    {
        return $this->user() instanceof Utilisateur;
    }

    public function teacher(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new \LogicException("L'enseignant authentifie est introuvable.");
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classe_id' => ['required', 'exists:classes,id'],
            'periode_id' => ['required', 'exists:modele_periodes,id'],
            'node_id' => ['required', 'exists:modele_nodes,id'],
            'type_evaluation_id' => ['required', 'exists:types_evaluations,id'],
            'attribution_cours_id' => ['sometimes', 'exists:attributions_cours,id'],
            'libelle' => ['required', 'string', 'max:200'],
            'maxima' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'coefficient' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'date_evaluation' => ['required', 'date'],
            'statut' => ['sometimes', Rule::in(StatutEvaluation::values())],
            'visible_parents_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
