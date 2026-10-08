<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Domains\Tenant\DTOs\RegisterAdministrateurData;
use App\Domains\Tenant\DTOs\RegisterEtablissementData;
use App\Domains\Tenant\DTOs\RegisterTenantData;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[FailOnUnknownFields]
final class RegisterTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom_espace' => ['required', 'string', 'max:150'],
            'type_tenant' => ['required', Rule::in(['ECOLE', 'GROUPE_ECOLES', 'ENSEIGNANT_INDIVIDUEL'])],
            'etablissement_nom' => ['required', 'string', 'max:180'],
            'etablissement_code_ministere' => ['nullable', 'string', 'max:80'],
            'etablissement_province_educationnelle' => ['nullable', 'string', 'max:150'],
            'etablissement_province' => ['nullable', 'string', 'max:100'],
            'etablissement_ville' => ['nullable', 'string', 'max:100'],
            'etablissement_commune_territoire' => ['nullable', 'string', 'max:100'],
            'etablissement_adresse' => ['nullable', 'string'],
            'etablissement_telephone' => ['nullable', 'string', 'max:30'],
            'etablissement_email' => ['nullable', 'email', 'max:150'],
            'admin_nom' => ['required', 'string', 'max:100'],
            'admin_postnom' => ['nullable', 'string', 'max:100'],
            'admin_prenom' => ['nullable', 'string', 'max:100'],
            'admin_email' => ['required', 'email', 'max:150'],
            'admin_telephone' => ['required', 'string', 'max:30'],
            'mot_de_passe' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function registrationData(): RegisterTenantData
    {
        return new RegisterTenantData(
            nomEspace: $this->string('nom_espace')->trim()->toString(),
            typeTenant: $this->string('type_tenant')->toString(),
            etablissement: new RegisterEtablissementData(
                nom: $this->string('etablissement_nom')->trim()->toString(),
                codeMinistere: $this->nullableString('etablissement_code_ministere'),
                provinceEducationnelle: $this->nullableString('etablissement_province_educationnelle'),
                province: $this->nullableString('etablissement_province'),
                ville: $this->nullableString('etablissement_ville'),
                communeTerritoire: $this->nullableString('etablissement_commune_territoire'),
                adresse: $this->nullableString('etablissement_adresse'),
                telephone: $this->nullableString('etablissement_telephone'),
                email: $this->nullableString('etablissement_email'),
            ),
            administrateur: new RegisterAdministrateurData(
                nom: $this->string('admin_nom')->trim()->toString(),
                postnom: $this->nullableString('admin_postnom'),
                prenom: $this->nullableString('admin_prenom'),
                email: $this->string('admin_email')->trim()->toString(),
                telephone: $this->string('admin_telephone')->trim()->toString(),
                motDePasse: $this->string('mot_de_passe')->toString(),
            ),
        );
    }

    private function nullableString(string $key): ?string
    {
        return $this->filled($key)
            ? $this->string($key)->trim()->toString()
            : null;
    }
}
