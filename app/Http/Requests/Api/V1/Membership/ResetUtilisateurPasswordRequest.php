<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\FormRequest;

final class ResetUtilisateurPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            && $user->actif
            && $user->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT', 'GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES']);
    }

    public function rules(): array
    {
        return [];
    }
}
