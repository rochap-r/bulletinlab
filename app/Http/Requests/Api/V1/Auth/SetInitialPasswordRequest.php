<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

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
            // Première connexion : l'ancien mot de passe temporaire n'est pas
            // redemandé, l'utilisateur renseigne uniquement le nouveau.
            'mot_de_passe' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }
}
