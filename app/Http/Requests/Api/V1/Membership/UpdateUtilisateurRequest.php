<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Utilisateur;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class UpdateUtilisateurRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Utilisateur|null $cible */
        $cible = $this->route('utilisateur');

        return $cible instanceof Utilisateur
            && $this->user()?->can('update', $cible) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'required', 'string', 'max:100'],
            'postnom' => ['sometimes', 'nullable', 'string', 'max:100'],
            'prenom' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sexe' => ['sometimes', 'nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['sometimes', 'nullable', 'date', 'before:today'],
            'lieu_naissance' => ['sometimes', 'nullable', 'string', 'max:150'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'adresse' => ['sometimes', 'nullable', 'string', 'max:255'],
            'matricule' => ['sometimes', 'nullable', 'string', 'max:50'],
            'qualification' => ['sometimes', 'nullable', 'string', 'max:150'],
            'specialite' => ['sometimes', 'nullable', 'string', 'max:150'],
            'date_embauche' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'role' => ['sometimes', 'string', Rule::in(['GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES', 'ENSEIGNANT', 'TITULAIRE', 'SECRETARIAT'])],
            'actif' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function updateData(): array
    {
        $data = [];

        foreach (['nom', 'postnom', 'prenom', 'sexe', 'lieu_naissance', 'email', 'adresse', 'matricule', 'qualification', 'specialite'] as $key) {
            if ($this->has($key)) {
                $value = $this->filled($key) ? trim((string) $this->input($key)) : null;
                $data[$key] = $value === '' ? null : $value;
            }
        }

        foreach (['date_naissance', 'date_embauche'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? (string) $this->input($key) : null;
            }
        }

        if ($this->has('role') && $this->filled('role')) {
            $data['role'] = (string) $this->input('role');
        }

        if ($this->has('actif')) {
            $data['actif'] = $this->boolean('actif');
        }

        if (array_key_exists('nom', $data) && $data['nom'] !== null) {
            $data['nom'] = trim($data['nom']);
        }

        return $data;
    }
}
