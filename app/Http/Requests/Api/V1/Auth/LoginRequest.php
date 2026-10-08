<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Domains\Auth\DTOs\LoginData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'tenant_slug' => ['required', 'string', 'max:100'],
            'identifiant' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    public function loginData(): LoginData
    {
        return new LoginData(
            tenantSlug: trim($this->string('tenant_slug')->toString()),
            identifiant: trim($this->string('identifiant')->toString()),
            password: $this->string('password')->toString(),
        );
    }
}
