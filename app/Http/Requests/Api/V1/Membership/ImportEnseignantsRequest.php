<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ImportEnseignantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Utilisateur::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'etablissement_id' => ['nullable', 'integer', Rule::exists('etablissements', 'id')],
            'enseignants' => ['required', 'array', 'min:1', 'max:500'],
            'enseignants.*' => ['required', 'array'],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function enseignantsRows(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->input('enseignants', []);

        return array_values($rows);
    }
}
