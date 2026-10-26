<?php

namespace App\Domains\Membership\Services;

use App\Domains\Membership\DTOs\CreatedDirectUtilisateurResult;
use App\Domains\Membership\DTOs\CreateDirectUtilisateurData;
use App\Domains\Membership\DTOs\RattacherUtilisateurData;
use App\Domains\Membership\DTOs\RattacherUtilisateurResult;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Models\AuditLog;
use App\Models\Etablissement;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class DirectUtilisateurService
{
    private const ADMIN_ASSIGNABLE_ROLES = ['GESTIONNAIRE_ECOLE', 'DIRECTEUR_ETUDES', 'ENSEIGNANT', 'TITULAIRE', 'SECRETARIAT'];

    private const MANAGER_ASSIGNABLE_ROLES = ['ENSEIGNANT', 'TITULAIRE', 'SECRETARIAT'];

    public function __construct(private Hasher $hasher, private TenantContext $tenantContext) {}

    public function create(CreateDirectUtilisateurData $data, Utilisateur $actor): CreatedDirectUtilisateurResult
    {
        return DB::transaction(function () use ($data, $actor): CreatedDirectUtilisateurResult {
            $tenantId = $this->tenantContext->id();
            $etablissementId = $this->resolveEtablissementId($data, $actor, $tenantId);

            $existing = Utilisateur::query()
                ->where('tenant_id', $tenantId)
                ->where('telephone_normalise', $data->telephone)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'telephone' => ['Ce numéro appartient déjà à cet espace. Rattachez un rôle au compte existant.'],
                ]);
            }

            if (! in_array($data->role, $this->assignableRoles($actor), true)) {
                throw ValidationException::withMessages([
                    'role' => ['Le rôle demandé ne peut pas être attribué par votre profil.'],
                ]);
            }

            if ($data->matricule !== null) {
                $matriculeExiste = Utilisateur::query()
                    ->where('tenant_id', $tenantId)
                    ->where('matricule', $data->matricule)
                    ->lockForUpdate()
                    ->exists();

                if ($matriculeExiste) {
                    throw ValidationException::withMessages([
                        'matricule' => ['Ce matricule existe déjà dans cet espace.'],
                    ]);
                }
            }

            $role = $this->assignableRole($tenantId, $data->role);

            $temporaryPassword = self::generateTemporaryPassword();

            try {
                $utilisateur = Utilisateur::query()->create([
                    'tenant_id' => $tenantId,
                    'etablissement_id' => $etablissementId,
                    'nom' => $data->nom,
                    'postnom' => $data->postnom,
                    'prenom' => $data->prenom,
                    'sexe' => $data->sexe,
                    'date_naissance' => $data->dateNaissance,
                    'lieu_naissance' => $data->lieuNaissance,
                    'email' => $data->email,
                    'telephone' => $data->telephone,
                    'adresse' => $data->adresse,
                    'matricule' => $data->matricule,
                    'qualification' => $data->qualification,
                    'specialite' => $data->specialite,
                    'date_embauche' => $data->dateEmbauche,
                    'mot_de_passe' => $this->hasher->make($temporaryPassword),
                    'doit_changer_mot_de_passe' => true,
                    'actif' => true,
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000') {
                    throw ValidationException::withMessages([
                        'telephone' => ['Ce numéro appartient déjà à cet espace. Rattachez un rôle au compte existant.'],
                    ]);
                }

                throw $exception;
            }

            $utilisateur->assignRole($role);
            $utilisateur->load(['roles', 'etablissement']);

            foreach (['UTILISATEUR_CREE_DIRECT', 'MDP_DEFAUT_GENERE'] as $action) {
                AuditLog::query()->create([
                    'tenant_id' => $tenantId,
                    'utilisateur_id' => $actor->getKey(),
                    'action' => $action,
                    'table_cible' => $utilisateur->getTable(),
                    'ligne_cible_id' => $utilisateur->getKey(),
                    'nouvelles_valeurs' => [
                        'role' => $role->code,
                        'etablissement_id' => $etablissementId,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            return new CreatedDirectUtilisateurResult($utilisateur, $temporaryPassword);
        }, attempts: 3);
    }

    public function search(string $telephone, Utilisateur $actor): array
    {
        $exists = Utilisateur::withoutGlobalScopes()
            ->where('telephone_normalise', $telephone)
            ->where('actif', true)
            ->orderBy('id')
            ->first();

        if ($exists === null) {
            return ['existe' => false, 'dans_espace_courant' => false, 'nom_masque' => null];
        }

        AuditLog::query()->create([
            'tenant_id' => $this->tenantContext->id(),
            'utilisateur_id' => $actor->getKey(),
            'action' => 'RECHERCHE_EXISTANT',
            'table_cible' => $exists->getTable(),
            'ligne_cible_id' => $exists->getKey(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return [
            'existe' => true,
            'dans_espace_courant' => Utilisateur::query()
                ->where('tenant_id', $this->tenantContext->id())
                ->where('telephone_normalise', $telephone)
                ->exists(),
            'nom_masque' => mb_substr($exists->nom, 0, 2).'***',
        ];
    }

    public function rattacher(RattacherUtilisateurData $data, Utilisateur $actor): RattacherUtilisateurResult
    {
        return DB::transaction(function () use ($data, $actor): RattacherUtilisateurResult {
            $tenantId = $this->tenantContext->id();

            if (! in_array($data->role, $this->assignableRoles($actor), true)) {
                throw ValidationException::withMessages([
                    'role' => ['Le rôle demandé ne peut pas être attribué par votre profil.'],
                ]);
            }

            $local = Utilisateur::query()
                ->where('tenant_id', $tenantId)
                ->where('telephone_normalise', $data->telephone)
                ->lockForUpdate()
                ->first();

            if ($local !== null) {
                $role = $this->assignableRole($tenantId, $data->role);

                $local->assignRole($role);
                $local->load(['roles', 'etablissement']);

                AuditLog::query()->create([
                    'tenant_id' => $tenantId,
                    'utilisateur_id' => $actor->getKey(),
                    'action' => 'UTILISATEUR_ROLE_RATTACHE',
                    'table_cible' => $local->getTable(),
                    'ligne_cible_id' => $local->getKey(),
                    'nouvelles_valeurs' => ['role' => $role->code],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return new RattacherUtilisateurResult($local, false);
            }

            $reference = Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $data->telephone)
                ->where('actif', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($reference === null) {
                throw ValidationException::withMessages([
                    'telephone' => ['Aucun compte actif avec ce numéro. Créez le compte directement.'],
                ]);
            }

            if (mb_strtolower(trim($reference->nom)) !== mb_strtolower($data->nom)) {
                throw ValidationException::withMessages([
                    'nom' => ['Le nom ne correspond pas au compte existant. Vérifiez le numéro.'],
                ]);
            }

            $etablissementId = $this->resolveEtablissementId(
                new CreateDirectUtilisateurData(
                    nom: $data->nom,
                    postnom: null,
                    prenom: null,
                    email: null,
                    telephone: $data->telephone,
                    role: $data->role,
                    etablissementId: $data->etablissementId,
                ),
                $actor,
                $tenantId,
            );

            $role = $this->assignableRole($tenantId, $data->role);

            try {
                $utilisateur = Utilisateur::query()->create([
                    'tenant_id' => $tenantId,
                    'etablissement_id' => $etablissementId,
                    'nom' => $reference->nom,
                    'postnom' => $reference->postnom,
                    'prenom' => $reference->prenom,
                    'email' => $reference->email,
                    'telephone' => $data->telephone,
                    'mot_de_passe' => $reference->mot_de_passe,
                    'doit_changer_mot_de_passe' => $reference->doit_changer_mot_de_passe,
                    'actif' => true,
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23000') {
                    throw ValidationException::withMessages([
                        'telephone' => ['Ce numéro appartient déjà à cet espace.'],
                    ]);
                }

                throw $exception;
            }

            $utilisateur->assignRole($role);
            $utilisateur->load(['roles', 'etablissement']);

            AuditLog::query()->create([
                'tenant_id' => $tenantId,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'UTILISATEUR_RATTACHE_EXISTANT',
                'table_cible' => $utilisateur->getTable(),
                'ligne_cible_id' => $utilisateur->getKey(),
                'nouvelles_valeurs' => [
                    'role' => $role->code,
                    'etablissement_id' => $etablissementId,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return new RattacherUtilisateurResult($utilisateur, true);
        }, attempts: 3);
    }

    public function update(Utilisateur $cible, array $data, Utilisateur $actor): Utilisateur
    {
        return DB::transaction(function () use ($cible, $data, $actor): Utilisateur {
            $tenantId = $this->tenantContext->id();

            if ((int) $cible->tenant_id !== $tenantId) {
                throw ValidationException::withMessages([
                    'utilisateur' => ['Ce compte n’appartient pas à votre espace.'],
                ]);
            }

            if (! $this->isTenantAdministrator($actor) && (int) $cible->etablissement_id !== (int) $actor->etablissement_id) {
                throw ValidationException::withMessages([
                    'utilisateur' => ['Vous ne pouvez modifier que les membres de votre établissement.'],
                ]);
            }

            if (array_key_exists('matricule', $data) && $data['matricule'] !== null && $data['matricule'] !== $cible->matricule) {
                $matriculeExiste = Utilisateur::query()
                    ->where('tenant_id', $tenantId)
                    ->where('matricule', $data['matricule'])
                    ->whereKeyNot($cible->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($matriculeExiste) {
                    throw ValidationException::withMessages([
                        'matricule' => ['Ce matricule existe déjà dans cet espace.'],
                    ]);
                }
            }

            if (array_key_exists('role', $data) && is_string($data['role']) && $data['role'] !== '') {
                if (! in_array($data['role'], $this->assignableRoles($actor), true)) {
                    throw ValidationException::withMessages([
                        'role' => ['Le rôle demandé ne peut pas être attribué par votre profil.'],
                    ]);
                }

                $role = $this->assignableRole($tenantId, $data['role']);
                $cible->assignRole($role);
                unset($data['role']);
            }

            $cible->fill($data);
            $cible->save();
            $cible->load(['roles', 'etablissement']);

            AuditLog::query()->create([
                'tenant_id' => $tenantId,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'UTILISATEUR_MIS_A_JOUR',
                'table_cible' => $cible->getTable(),
                'ligne_cible_id' => $cible->getKey(),
                'nouvelles_valeurs' => $data,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $cible;
        }, attempts: 3);
    }

    private function assignableRole(int $tenantId, string $code): Role
    {
        return Role::query()
            ->where(function (Builder $query) use ($code): void {
                $query->where('name', $code)->orWhere('code', $code);
            })
            ->validForTenant($tenantId)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->first()
            ?? throw ValidationException::withMessages([
                'role' => ['Le rôle demandé ne peut pas être attribué par votre profil.'],
            ]);
    }

    private function resolveEtablissementId(CreateDirectUtilisateurData $data, Utilisateur $actor, int $tenantId): int
    {
        if ($this->isTenantAdministrator($actor)) {
            $etablissementId = $data->etablissementId;

            if ($etablissementId === null) {
                throw ValidationException::withMessages(['etablissement_id' => ['Un établissement actif est requis.']]);
            }
        } else {
            if ($data->etablissementId !== null && $data->etablissementId !== $actor->etablissement_id) {
                throw ValidationException::withMessages(['etablissement_id' => ['Vous ne pouvez créer que dans votre établissement.']]);
            }

            $etablissementId = $actor->etablissement_id;

            if ($etablissementId === null) {
                throw ValidationException::withMessages(['etablissement_id' => ['Un établissement actif est requis.']]);
            }
        }

        return Etablissement::query()
            ->whereKey($etablissementId)
            ->where('tenant_id', $tenantId)
            ->where('actif', true)
            ->value('id')
            ?? throw ValidationException::withMessages(['etablissement_id' => ['L’établissement sélectionné est invalide.']]);
    }

    /** @return list<string> */
    private function assignableRoles(Utilisateur $actor): array
    {
        return $this->isTenantAdministrator($actor)
            ? self::ADMIN_ASSIGNABLE_ROLES
            : self::MANAGER_ASSIGNABLE_ROLES;
    }

    private static function generateTemporaryPassword(): string
    {
        return Str::password(length: 16, spaces: false);
    }

    private function isTenantAdministrator(Utilisateur $actor): bool
    {
        return $actor->hasTenantRole(['ADMIN_ECOLE', 'ADMIN_TENANT']);
    }
}
