<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreClasseRequest extends FormRequest
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
            'etablissement_id' => 'required|integer|exists:etablissements,id',
            'annee_scolaire_id' => 'required|integer|exists:annees_scolaires,id',
            'cycle_id' => 'required|integer|exists:ref_cycles,id',
            'niveau_id' => 'required|integer|exists:ref_niveaux,id',
            'nom' => 'required|string',
            'parallele' => 'nullable|string',
            'code' => 'nullable|string',
        ];
    }
}
