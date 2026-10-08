<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\DTOs\AuthenticatedSession;
use App\Domains\Auth\DTOs\LoginData;
use App\Domains\Auth\Exceptions\AuthenticationException;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService implements AuthServiceInterface
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private Hasher $hasher,
    ) {}

    public function login(LoginData $data): AuthenticatedSession
    {
        $tenant = Tenant::query()
            ->where('slug', $data->tenantSlug)
            ->whereIn('statut', ['EN_ATTENTE', 'ESSAI', 'ACTIF'])
            ->first();

        $utilisateur = Utilisateur::query()
            ->where('tenant_id', $tenant?->id ?? 0)
            ->where('telephone', $data->identifiant)
            ->first();

        $passwordMatches = $this->hasher->check(
            $data->password,
            $utilisateur?->mot_de_passe ?? self::DUMMY_PASSWORD_HASH,
        );

        if (! $tenant || ! $utilisateur || ! $utilisateur->actif || ! $passwordMatches) {
            throw new AuthenticationException('Invalid credentials');
        }

        $accessToken = $utilisateur
            ->createToken('tenant-auth', ["tenant:{$tenant->id}"])
            ->plainTextToken;

        return new AuthenticatedSession($utilisateur, $accessToken);
    }

    public function logout(Utilisateur $utilisateur): void
    {
        $accessToken = $utilisateur->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
    }
}
