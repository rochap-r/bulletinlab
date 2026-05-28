<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEleveRequest extends FormRequest
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
            'nom' => 'required|string',
            'postnom' => 'nullable|string',
            'prenom' => 'nullable|string',
            'sexe' => 'nullable|string',
            'date_naissance' => 'nullable|date',
        ];
    }
}
