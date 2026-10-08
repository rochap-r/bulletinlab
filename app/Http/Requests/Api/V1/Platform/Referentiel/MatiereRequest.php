<?php

namespace App\Http\Requests\Api\V1\Platform\Referentiel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class MatiereRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:120', Rule::unique('ref_matieres')->ignore($this->route('matiere'))],
            'nom' => ['required', 'string', 'max:180'],
            'nom_court' => ['sometimes', 'nullable', 'string', 'max:80'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
