<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Utilisateur;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class RechercherUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Utilisateur::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'telephone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('telephone'))) {
            return;
        }

        try {
            $this->merge(['telephone' => PhoneNumberNormalizer::normalize($this->input('telephone'))]);
        } catch (\InvalidArgumentException) {
        }
    }
}
