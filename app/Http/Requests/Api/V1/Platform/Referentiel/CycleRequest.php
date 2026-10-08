<?php

namespace App\Http\Requests\Api\V1\Platform\Referentiel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class CycleRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique('ref_cycles')->ignore($this->route('cycle'))],
            'nom' => ['required', 'string', 'max:120'],
            'ordre' => ['sometimes', 'integer', 'min:1'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
