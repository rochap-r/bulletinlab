<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

final class ImportEnseignantsExcelRequest extends FormRequest
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
            'fichier' => [
                'required',
                File::types(['xlsx', 'xls'])->max('10mb'),
                'extensions:xlsx,xls',
            ],
        ];
    }
}
