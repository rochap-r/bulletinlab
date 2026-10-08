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
            'statut' => ['nullable', Rule::in(['EN_ATTENTE', 'ESSAI', 'ACTIF', 'SUSPENDU', 'RESILIE', 'REFUSE'])],
        ];
    }
}
