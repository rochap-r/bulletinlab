<?php

namespace App\Http\Requests\Api\V1\Onboarding;

use App\Domains\Tenant\DTOs\RegisterAdministrateurData;
use App\Domains\Tenant\DTOs\RegisterEtablissementData;
use App\Domains\Tenant\DTOs\RegisterTenantData;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'etablissement_nom' => ['nullable', 'string', 'max:180'],
            'etablissement_type_etablissement' => ['nullable', Rule::in(['PRIMAIRE', 'SECONDAIRE', 'MATERNELLE', 'MIXTE'])],
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
            'admin_email' => ['nullable', 'email', 'max:150'],
            'admin_telephone' => ['required', 'string', 'max:16', 'regex:/^\+[1-9]\d{7,14}$/'],
            'mot_de_passe' => ['required', 'string', 'max:255', 'confirmed', Password::defaults()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('etablissement_nom')) {
                return;
            }

            $orphans = array_filter([
                'etablissement_type_etablissement',
                'etablissement_code_ministere',
                'etablissement_province_educationnelle',
                'etablissement_province',
                'etablissement_ville',
                'etablissement_commune_territoire',
                'etablissement_adresse',
                'etablissement_telephone',
                'etablissement_email',
            ], fn (string $key): bool => $this->filled($key));

            if ($orphans !== []) {
                $validator->errors()->add(
                    'etablissement_nom',
                    'Le nom du premier établissement est requis quand ses informations sont renseignées.'
                );
            }
        });
    }

    public function registrationData(): RegisterTenantData
    {
        return new RegisterTenantData(
            nomEspace: $this->string('nom_espace')->trim()->toString(),
            typeTenant: $this->string('type_tenant')->toString(),
            etablissement: $this->filled('etablissement_nom')
                ? new RegisterEtablissementData(
                    nom: $this->string('etablissement_nom')->trim()->toString(),
                    codeMinistere: $this->nullableString('etablissement_code_ministere'),
                    provinceEducationnelle: $this->nullableString('etablissement_province_educationnelle'),
                    province: $this->nullableString('etablissement_province'),
                    ville: $this->nullableString('etablissement_ville'),
                    communeTerritoire: $this->nullableString('etablissement_commune_territoire'),
                    adresse: $this->nullableString('etablissement_adresse'),
                    telephone: $this->nullableString('etablissement_telephone'),
                    email: $this->nullableString('etablissement_email'),
                    type: $this->filled('etablissement_type_etablissement')
                        ? $this->string('etablissement_type_etablissement')->toString()
                        : null,
                )
                : null,
            administrateur: new RegisterAdministrateurData(
                nom: $this->string('admin_nom')->trim()->toString(),
                postnom: $this->nullableString('admin_postnom'),
                prenom: $this->nullableString('admin_prenom'),
                email: $this->nullableString('admin_email'),
                telephone: $this->string('admin_telephone')->trim()->toString(),
                motDePasse: $this->string('mot_de_passe')->toString(),
            ),
        );
    }

    protected function prepareForValidation(): void
    {
        if (! is_string($this->input('admin_telephone'))) {
            return;
        }

        try {
            $this->merge([
                'admin_telephone' => PhoneNumberNormalizer::normalize($this->input('admin_telephone')),
            ]);
        } catch (\InvalidArgumentException) {
        }
    }

    private function nullableString(string $key): ?string
    {
        return $this->filled($key)
            ? $this->string($key)->trim()->toString()
            : null;
    }
}
