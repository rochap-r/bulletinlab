<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Contracts\TenantRegistrationServiceInterface;
use App\Domains\Tenant\DTOs\RegisteredTenantResult;
use App\Domains\Tenant\DTOs\RegisterTenantData;
use App\Models\AuditLog;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class TenantRegistrationService implements TenantRegistrationServiceInterface
{
    public function __construct(private readonly Hasher $hasher) {}

    public function register(RegisterTenantData $data): RegisteredTenantResult
    {
        return DB::transaction(function () use ($data): RegisteredTenantResult {
            $tenant = Tenant::query()->create([
                'uuid' => (string) Str::uuid(),
                'nom_espace' => $data->nomEspace,
                'slug' => $this->uniqueSlug($data->nomEspace),
                'type_tenant' => $data->typeTenant,
                'statut' => 'EN_ATTENTE',
            ]);

            $etablissement = Etablissement::query()->create([
                'tenant_id' => $tenant->getKey(),
                'nom' => $data->etablissement->nom,
                'code_ministere' => $data->etablissement->codeMinistere,
                'province_educationnelle' => $data->etablissement->provinceEducationnelle,
                'province' => $data->etablissement->province,
                'ville' => $data->etablissement->ville,
                'commune_territoire' => $data->etablissement->communeTerritoire,
                'adresse' => $data->etablissement->adresse,
                'telephone' => $data->etablissement->telephone,
                'email' => $data->etablissement->email,
                'actif' => true,
            ]);

            $role = Role::query()->firstOrCreate(
                ['tenant_id' => $tenant->getKey(), 'code' => 'ADMIN_ECOLE'],
                ['nom' => 'Administrateur école', 'system_role' => false],
            );

            $utilisateur = Utilisateur::query()->create([
                'tenant_id' => $tenant->getKey(),
                'etablissement_id' => $etablissement->getKey(),
                'nom' => $data->administrateur->nom,
                'postnom' => $data->administrateur->postnom,
                'prenom' => $data->administrateur->prenom,
                'email' => $data->administrateur->email,
                'telephone' => $data->administrateur->telephone,
                'mot_de_passe' => $this->hasher->make($data->administrateur->motDePasse),
                'actif' => true,
            ]);

            $utilisateur->roles()->attach($role->getKey());

            AuditLog::query()->create([
                'tenant_id' => $tenant->getKey(),
                'utilisateur_id' => $utilisateur->getKey(),
                'action' => 'TENANT_INSCRIT_EN_ATTENTE',
                'table_cible' => $tenant->getTable(),
                'ligne_cible_id' => $tenant->getKey(),
                'nouvelles_valeurs' => [
                    'etablissement_id' => $etablissement->getKey(),
                    'administrateur_id' => $utilisateur->getKey(),
                ],
            ]);

            return new RegisteredTenantResult($tenant, $etablissement, $utilisateur);
        }, attempts: 3);
    }

    private function uniqueSlug(string $nom): string
    {
        $base = Str::slug($nom);
        $slug = $base;
        $suffix = 1;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
