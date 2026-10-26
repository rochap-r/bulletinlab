<?php

namespace App\Http\Requests\Api\V1\PlatformAuth;

use App\Domains\Auth\DTOs\PlatformLoginData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;

#[FailOnUnknownFields]
class PlatformLoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email:strict', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function loginData(): PlatformLoginData
    {
        return new PlatformLoginData(
            identifiant: $this->string('email')->trim()->lower()->toString(),
            password: $this->string('password')->toString(),
        );
    }
}
