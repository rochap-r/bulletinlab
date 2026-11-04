<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\StatutEvaluation;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EvaluationIndexRequest extends FormRequest
{
    public function authorize(Gate $gate, EstablishmentContext $context): bool
    {
        return $this->user() !== null;
    }

    public function teacher(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new \LogicException("L'enseignant authentifié est introuvable.");
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classe_id' => ['sometimes', 'exists:classes,id'],
            'periode_id' => ['sometimes', 'exists:modele_periodes,id'],
            'node_id' => ['sometimes', 'exists:modele_nodes,id'],
            'type_evaluation_id' => ['sometimes', 'exists:types_evaluations,id'],
            'statut' => ['sometimes', Rule::in(StatutEvaluation::values())],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
