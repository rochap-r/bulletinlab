<?php

namespace App\Http\Requests\Api\V1\Platform;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class PlatformEtablissementRequest extends FormRequest
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
            'nom' => ['required', 'string', 'max:180'],
            'code_ministere' => ['nullable', 'string', 'max:80'],
            'province_educationnelle' => ['nullable', 'string', 'max:150'],
            'province' => ['nullable', 'string', 'max:100'],
            'ville' => ['nullable', 'string', 'max:100'],
            'commune_territoire' => ['nullable', 'string', 'max:100'],
            'adresse' => ['nullable', 'string'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
