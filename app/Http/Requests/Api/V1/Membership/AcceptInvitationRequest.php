<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Domains\Membership\DTOs\AcceptanceData;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
final class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:64'],
            'nom' => ['required', 'string', 'max:100'],
            'postnom' => ['nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
            'mot_de_passe' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    public function acceptanceData(): AcceptanceData
    {
        return new AcceptanceData(
            token: $this->string('token')->trim()->toString(),
            nom: $this->string('nom')->trim()->toString(),
            postnom: $this->filled('postnom')
                ? $this->string('postnom')->trim()->toString()
                : null,
            prenom: $this->filled('prenom')
                ? $this->string('prenom')->trim()->toString()
                : null,
            telephone: $this->string('telephone')->trim()->toString(),
            motDePasse: $this->string('mot_de_passe')->toString(),
        );
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('telephone'))) {
            return;
        }

        try {
            $this->merge([
                'telephone' => PhoneNumberNormalizer::normalize($this->input('telephone')),
            ]);
        } catch (\InvalidArgumentException) {
        }
    }
}
