<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'tenant_id' => 'required|integer|exists:tenants,id',
            'nom' => 'required|string',
            'telephone' => 'required|string',
            'password' => 'required|string|min:6',
            'postnom' => 'nullable|string',
            'prenom' => 'nullable|string',
            'email' => 'nullable|email',
            'etablissement_id' => 'nullable|integer|exists:etablissements,id',
        ];
    }
}
