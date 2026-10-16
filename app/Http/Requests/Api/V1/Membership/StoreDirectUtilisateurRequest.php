<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Domains\Membership\DTOs\CreateDirectUtilisateurData;
use App\Models\Utilisateur;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class StoreDirectUtilisateurRequest extends FormRequest
{
    private ?bool $tenantAdministrator = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Utilisateur::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:100'],
            'postnom' => ['nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'sexe' => ['nullable', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'telephone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'specialite' => ['nullable', 'string', 'max:150'],
            'date_embauche' => ['nullable', 'date', 'before_or_equal:today'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'etablissement_id' => [
                Rule::requiredIf($this->isTenantAdministrator()),
                Rule::prohibitedIf(! $this->isTenantAdministrator()),
                'integer',
                Rule::exists('etablissements', 'id')->where('tenant_id', $this->user()?->tenant_id)->where('actif', true),
            ],
        ];
    }

    public function creationData(): CreateDirectUtilisateurData
    {
        return new CreateDirectUtilisateurData(
            nom: $this->string('nom')->trim()->toString(),
            postnom: $this->nullableString('postnom'),
            prenom: $this->nullableString('prenom'),
            email: $this->nullableString('email'),
            telephone: $this->string('telephone')->toString(),
            role: $this->string('role')->toString(),
            etablissementId: $this->integer('etablissement_id') ?: null,
            sexe: $this->filled('sexe') ? $this->string('sexe')->toString() : null,
            dateNaissance: $this->filled('date_naissance') ? $this->string('date_naissance')->toString() : null,
            lieuNaissance: $this->nullableString('lieu_naissance'),
            adresse: $this->nullableString('adresse'),
            matricule: $this->nullableString('matricule'),
            qualification: $this->nullableString('qualification'),
            specialite: $this->nullableString('specialite'),
            dateEmbauche: $this->filled('date_embauche') ? $this->string('date_embauche')->toString() : null,
        );
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('telephone'))) {
            return;
        }

        try {
            $this->merge(['telephone' => PhoneNumberNormalizer::normalize($this->input('telephone'))]);
        } catch (\InvalidArgumentException) {
        }
    }

    /** @return list<string> */
    private function assignableRoles(): array
    {
        return $this->isTenantAdministrator()
            ? ['GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES', 'ENSEIGNANT', 'TITULAIRE', 'SECRETARIAT']
            : ['ENSEIGNANT', 'TITULAIRE', 'SECRETARIAT'];
    }

    private function isTenantAdministrator(): bool
    {
        if ($this->tenantAdministrator !== null) {
            return $this->tenantAdministrator;
        }

        $user = $this->user();

        return $this->tenantAdministrator = $user instanceof Utilisateur
            && $user->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
    }

    private function nullableString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }
}
