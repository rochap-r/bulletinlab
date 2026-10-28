<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Enums\StatutEvaluation;
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
            'per_page' => ['sometimes', 'integer:strict', 'min:1', 'max:100'],
        ];
    }
}
