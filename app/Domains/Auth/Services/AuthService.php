<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\DTOs\AuthenticatedSession;
use App\Domains\Auth\DTOs\GlobalLoginResult;
use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\Exceptions\AuthenticationException;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

class AuthService implements AuthServiceInterface
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private Hasher $hasher,
    ) {}

    public function login(LoginData $data): AuthenticatedSession
    {
        $tenant = $data->tenantId !== null
            ? Tenant::query()
                ->whereKey($data->tenantId)
                ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
                ->first()
            : Tenant::query()
                ->where('slug', $data->tenantSlug ?? '')
                ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
                ->first();

        $utilisateur = Utilisateur::query()
            ->where('tenant_id', $tenant?->id ?? 0)
            ->where(function ($query) use ($data): void {
                $query->where('telephone', $data->identifiant)
                    ->orWhere('telephone_normalise', $data->identifiant);
            })
            ->first();

        $passwordMatches = $this->hasher->check(
            $data->password,
            $utilisateur?->mot_de_passe ?? self::DUMMY_PASSWORD_HASH,
        );

        if (! $tenant || ! $utilisateur || ! $utilisateur->actif || ! $passwordMatches) {
            Log::channel('security')->warning('auth.login.failed', [
                'tenant_id' => $tenant?->id,
                'tenant_slug' => $data->tenantSlug,
            ]);

            throw new AuthenticationException('Invalid credentials');
        }

        $utilisateur->forceFill(['derniere_connexion_at' => now()])->save();

        Log::channel('security')->info('auth.login', [
            'tenant_id' => $tenant->id,
            'user_id' => $utilisateur->id,
        ]);

        return $this->issueSession($utilisateur, $tenant);
    }

    public function logout(Utilisateur $utilisateur): void
    {
        $accessToken = $utilisateur->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
    }

    public function switchTenant(Utilisateur $user, int $tenantId): AuthenticatedSession
    {
        $target = blank($user->telephone_normalise)
            ? null
            : Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $user->telephone_normalise)
                ->where('tenant_id', $tenantId)
                ->where('actif', true)
                ->first();

        $tenant = $target === null
            ? null
            : Tenant::query()
                ->whereKey($tenantId)
                ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
                ->first();

        if ($target === null || $tenant === null) {
            Log::channel('security')->warning('auth.switch.failed', [
                'user_id' => $user->getKey(),
                'target_tenant_id' => $tenantId,
            ]);

            throw new AuthenticationException('Invalid credentials');
        }

        AuditLog::query()->create([
            'tenant_id' => $tenant->getKey(),
            'utilisateur_id' => $target->getKey(),
            'action' => 'TENANT_SWITCH',
            'table_cible' => $target->getTable(),
            'ligne_cible_id' => $target->getKey(),
        ]);

        Log::channel('security')->info('auth.switch', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $target->getKey(),
        ]);

        return $this->issueSession($target, $tenant);
    }

    private function issueSession(Utilisateur $utilisateur, Tenant $tenant): AuthenticatedSession
    {
        $accessToken = $utilisateur
            ->createToken('tenant-auth', ["tenant:{$tenant->id}"], now()->addMinutes((int) config('sanctum.expiration', 43200)))
            ->plainTextToken;

        setPermissionsTeamId($tenant->getKey());
        $utilisateur->unsetRelation('roles');
        $utilisateur->loadMissing(['roles.permissions', 'etablissement']);
        $utilisateur->setRelation('tenant', $tenant);

        return new AuthenticatedSession($utilisateur, $accessToken);
    }

    /** @return array<int, string> */
    private function scopedRoleCodes(Utilisateur $utilisateur): array
    {
        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;
        $registrar->teams = false;

        try {
            return $utilisateur->roles()
                ->validForTenant($utilisateur->tenant_id)
                ->pluck('roles.name')
                ->unique()->values()->all();
        } finally {
            $registrar->teams = $teams;
        }
    }

    /** @return array<int, string> */
    private function scopedPermissionCodes(Utilisateur $utilisateur): array
    {
        $registrar = app(PermissionRegistrar::class);
        $teams = $registrar->teams;
        $registrar->teams = false;

        try {
            return $utilisateur->roles()
                ->validForTenant($utilisateur->tenant_id)
                ->with('permissions')
                ->get()
                ->flatMap(fn (Role $role): array => $role->permissions->pluck('name')->all())
                ->unique()->values()->all();
        } finally {
            $registrar->teams = $teams;
        }
    }

    public function logoutAll(Utilisateur $utilisateur): void
    {
        $utilisateur->tokens()->delete();
    }

    /**
     * Vérifie le mot de passe sur l'ensemble des appartenances du numéro.
     * Un seul espace → session complète. Plusieurs → memberships + jeton
     * de sélection à usage unique (5 min, ability tenant:select).
     */
    public function loginGlobal(string $identifiant, string $password): GlobalLoginResult
    {
        $rows = blank($identifiant)
            ? collect()
            : Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $identifiant)
                ->where('actif', true)
                ->whereHas('tenant', fn ($query) => $query->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF']))
                ->with(['roles.permissions', 'tenant'])
                ->orderBy('id')
                ->get();

        $matched = $rows->filter(fn (Utilisateur $row): bool => $this->hasher->check(
            $password,
            $row->mot_de_passe ?? self::DUMMY_PASSWORD_HASH,
        ));

        if ($matched->isEmpty()) {
            $this->hasher->check($password, self::DUMMY_PASSWORD_HASH);

            Log::channel('security')->warning('auth.login.failed', ['spaces' => 0]);

            throw new AuthenticationException('Invalid credentials');
        }

        $memberships = $matched->map(fn (Utilisateur $row): array => [
            'tenant_id' => $row->tenant_id,
            'slug' => $row->tenant->slug,
            'nom_espace' => $row->tenant->nom_espace,
            'roles' => $this->scopedRoleCodes($row),
            'permissions' => $this->scopedPermissionCodes($row),
        ])->values()->all();

        if ($matched->count() === 1) {
            /** @var Utilisateur $only */
            $only = $matched->first();
            $only->forceFill(['derniere_connexion_at' => now()])->save();

            Log::channel('security')->info('auth.login', [
                'tenant_id' => $only->tenant_id,
                'user_id' => $only->id,
                'spaces' => 1,
            ]);

            return new GlobalLoginResult($this->issueSession($only, $only->tenant), $memberships, null);
        }

        /** @var Utilisateur $selector */
        $selector = $matched->first();

        $selectToken = $selector
            ->createToken('tenant-select', ['tenant:select'], now()->addMinutes(5))
            ->plainTextToken;

        Log::channel('security')->info('auth.login.select_required', [
            'user_id' => $selector->id,
            'spaces' => $matched->count(),
        ]);

        return new GlobalLoginResult(null, $memberships, $selectToken);
    }

    /**
     * Convertit un jeton de sélection (mot de passe déjà vérifié) en session
     * complète sur l'espace choisi, puis révoque le jeton de sélection.
     */
    public function selectTenant(Utilisateur $selector, int $tenantId): AuthenticatedSession
    {
        $selectToken = $selector->currentAccessToken();

        if (! $selectToken instanceof PersonalAccessToken || ! $selectToken->can('tenant:select')) {
            throw new AuthenticationException('Invalid credentials');
        }

        $target = blank($selector->telephone_normalise)
            ? null
            : Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $selector->telephone_normalise)
                ->where('tenant_id', $tenantId)
                ->where('actif', true)
                ->first();

        $tenant = $target === null
            ? null
            : Tenant::query()
                ->whereKey($tenantId)
                ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
                ->first();

        if ($target === null || $tenant === null) {
            Log::channel('security')->warning('auth.select.failed', [
                'user_id' => $selector->getKey(),
                'target_tenant_id' => $tenantId,
            ]);

            throw new AuthenticationException('Invalid credentials');
        }

        $selectToken->delete();

        $target->forceFill(['derniere_connexion_at' => now()])->save();

        AuditLog::query()->create([
            'tenant_id' => $tenant->getKey(),
            'utilisateur_id' => $target->getKey(),
            'action' => 'TENANT_SWITCH',
            'table_cible' => $target->getTable(),
            'ligne_cible_id' => $target->getKey(),
        ]);

        Log::channel('security')->info('auth.select', [
            'tenant_id' => $tenant->getKey(),
            'user_id' => $target->getKey(),
        ]);

        return $this->issueSession($target, $tenant);
    }
}
