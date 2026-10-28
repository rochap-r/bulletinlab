<?php

namespace App\Http\Requests\Api\V1\Evaluation;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class StoreEvaluationResultatsRequest extends FormRequest
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
        $statuses = NoteResultStatus::teacherEditableValues();

        return [
            'resultats' => ['required', 'array', 'min:1', 'max:200'],
            'resultats.*.inscription_id' => ['required', 'exists:inscriptions,id'],
            'resultats.*.statut_resultat' => ['sometimes', Rule::in($statuses)],
            'resultats.*.valeur' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'resultats.*.commentaire_parent' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'resultats.*.commentaire_interne' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resultats.required' => 'Au moins un resultat est requis.',
            'resultats.min' => 'Au moins un resultat est requis.',
        ];
    }

    /**
     * @return list<array{inscription_id: int, statut_resultat: string, valeur: float|null, commentaire_parent?: string|null, commentaire_interne?: string|null}>
     */
    public function resultats(): array
    {
        return array_map(
            static function (array $resultat): array {
                return [
                    'inscription_id' => (int) $resultat['inscription_id'],
                    'statut_resultat' => $resultat['statut_resultat'] ?? NoteResultStatus::Saisie->value,
                    'valeur' => $resultat['valeur'] ?? null,
                    'commentaire_parent' => $resultat['commentaire_parent'] ?? null,
                    'commentaire_interne' => $resultat['commentaire_interne'] ?? null,
                ];
            },
            $this->validated('resultats'),
        );
    }
}
