<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class PlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom_espace' => ['required', 'string', 'max:150'],
            'type_tenant' => ['required', Rule::in(['ECOLE', 'GROUPE_ECOLES', 'ENSEIGNANT_INDIVIDUEL'])],
            // Les statuts de validation (EN_ATTENTE, ACTIF, REFUSE) transitent
            // exclusivement par activation / refus / réexamen (audités).
            'statut' => ['nullable', Rule::in(['ESSAI', 'SUSPENDU', 'RESILIE'])],
            'espace_parents_actif' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'statut.in' => 'Le statut EN_ATTENTE, ACTIF ou REFUSE relève de la validation (activation, refus, réexamen).',
        ];
    }
}
