<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Auth\DTOs\LoginData;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_slug' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'identifiant' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function loginData(): LoginData
    {
        return new LoginData(
            tenantSlug: $this->filled('tenant_slug') ? trim($this->string('tenant_slug')->toString()) : null,
            tenantId: $this->integer('tenant_id') ?: null,
            identifiant: $this->normalizedIdentifiant(),
            password: $this->string('password')->toString(),
        );
    }

    private function normalizedIdentifiant(): string
    {
        $identifiant = trim($this->string('identifiant')->toString());

        try {
            return PhoneNumberNormalizer::normalize($identifiant);
        } catch (\InvalidArgumentException) {
            return $identifiant;
        }
    }
}
