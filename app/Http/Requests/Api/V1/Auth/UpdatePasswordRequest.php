<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'mot_de_passe_actuel' => ['required', 'string', 'max:255'],
            'mot_de_passe' => ['required', 'string', 'min:6', 'max:255', 'confirmed', 'different:mot_de_passe_actuel'],
        ];
    }
}
