<?php

namespace App\Http\Requests\Api\V1\Platform\Referentiel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class NiveauRequest extends FormRequest
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
            'degre_id' => [
                'nullable',
                'integer',
                Rule::exists('ref_degres', 'id')
                    ->where(fn (Builder $query): Builder => $query->where('cycle_id', $cycle)),
            ],
            'code' => ['required', 'string', 'max:50', Rule::unique('ref_niveaux')->ignore($this->route('niveau'))],
            'nom' => ['required', 'string', 'max:120'],
            'numero' => ['required', 'integer', 'min:1', 'max:255'],
            'ordre' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
