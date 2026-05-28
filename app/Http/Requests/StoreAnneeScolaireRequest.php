<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnneeScolaireRequest extends FormRequest
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
            'calendrier_id' => 'required|integer|exists:calendriers_scolaires,id',
            'libelle' => 'required|string',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'cycles' => 'nullable|array',
        ];
    }
}
