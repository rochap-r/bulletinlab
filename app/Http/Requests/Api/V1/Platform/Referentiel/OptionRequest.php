<?php

namespace App\Http\Requests\Api\V1\Platform\Referentiel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class OptionRequest extends FormRequest
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
        $cycle = $this->route('cycle');

        return [
            'cycle_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('ref_cycles', 'id')
                    ->where(fn (Builder $query): Builder => $query->where('id', $cycle)),
            ],
            'code' => ['required', 'string', 'max:80', Rule::unique('ref_options')->ignore($this->route('option'))],
            'nom' => ['required', 'string', 'max:150'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
