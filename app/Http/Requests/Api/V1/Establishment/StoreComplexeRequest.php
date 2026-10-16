<?php

namespace App\Http\Requests\Api\V1\Establishment;

use App\Models\Etablissement;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class StoreComplexeRequest extends FormRequest
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
            // 150 caractères max : même suffixé (« … Secondaire »),
            // le nom composé reste sous la limite de 180.
            'nom_base' => ['required', 'string', 'max:150'],
            'types' => ['required', 'array', 'min:1', 'max:3'],
            'types.*' => ['distinct', Rule::in(['MATERNELLE', 'PRIMAIRE', 'SECONDAIRE'])],
        ];
    }

    /**
     * @return list<string>
     */
    public function orderedTypes(): array
    {
        $order = ['MATERNELLE' => 0, 'PRIMAIRE' => 1, 'SECONDAIRE' => 2];
        $types = array_values(array_unique($this->array('types')));

        usort($types, fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        return $types;
    }
}
