<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class SetInitialPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Première connexion : l'ancien mot de passe (123456) n'est pas
            // redemandé, l'utilisateur renseigne uniquement le nouveau.
            'mot_de_passe' => ['required', 'string', 'min:6', 'max:255', 'confirmed', Rule::notIn(['123456'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'mot_de_passe.not_in' => 'Choisissez un mot de passe différent de 123456.',
        ];
    }
}
