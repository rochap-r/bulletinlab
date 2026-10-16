<?php

namespace App\Http\Requests\Api\V1\Establishment;

use App\Models\Etablissement;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class StoreEtablissementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Etablissement::class) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:180'],
            'type_etablissement' => ['required', Rule::in(['PRIMAIRE', 'SECONDAIRE', 'MATERNELLE', 'MIXTE'])],
            'code_interne' => ['nullable', 'string', 'max:80', Rule::unique('etablissements')->where('tenant_id', $this->user()?->tenant_id)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('code_interne') && trim((string) $this->input('code_interne')) === '') {
            $this->merge(['code_interne' => null]);
        }
    }
}
