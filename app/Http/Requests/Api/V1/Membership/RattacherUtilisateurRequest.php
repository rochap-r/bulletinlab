<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Domains\Membership\DTOs\RattacherUtilisateurData;
use App\Models\Utilisateur;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class RattacherUtilisateurRequest extends FormRequest
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
            'telephone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
            'nom' => ['required', 'string', 'max:100'],
            'role' => ['required', Rule::in($this->assignableRoles())],
            'etablissement_id' => [
                Rule::requiredIf($this->isTenantAdministrator()),
                Rule::prohibitedIf(! $this->isTenantAdministrator()),
                'integer',
                Rule::exists('etablissements', 'id')->where('tenant_id', $this->user()?->tenant_id)->where('actif', true),
            ],
        ];
    }

    public function rattachementData(): RattacherUtilisateurData
    {
        return new RattacherUtilisateurData(
            telephone: $this->string('telephone')->toString(),
            nom: $this->string('nom')->trim()->toString(),
            role: $this->string('role')->toString(),
            etablissementId: $this->integer('etablissement_id') ?: null,
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
}
