<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\PlatformAuthServiceInterface;
use App\Domains\Auth\DTOs\PlatformAuthenticatedSession;
use App\Domains\Auth\DTOs\PlatformLoginData;
use App\Domains\Auth\Exceptions\PlatformAuthenticationException;
use App\Models\UtilisateurPlateforme;
use Illuminate\Contracts\Hashing\Hasher;
use Laravel\Sanctum\PersonalAccessToken;

class PlatformAuthService implements PlatformAuthServiceInterface
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private Hasher $hasher,
    ) {}

    public function login(PlatformLoginData $data): PlatformAuthenticatedSession
    {
        $utilisateur = UtilisateurPlateforme::query()
            ->where('email', $data->identifiant)
            ->first();

        $passwordMatches = $this->hasher->check(
            $data->password,
            $utilisateur?->mot_de_passe ?? self::DUMMY_PASSWORD_HASH,
        );

        if (! $utilisateur || ! $utilisateur->isActive() || ! $passwordMatches) {
            throw new PlatformAuthenticationException('Invalid credentials');
        }

        $accessToken = $utilisateur
            ->createToken('platform-auth', ['context:platform'])
            ->plainTextToken;

        return new PlatformAuthenticatedSession($utilisateur, $accessToken);
    }

    public function logout(UtilisateurPlateforme $utilisateur): void
    {
        $accessToken = $utilisateur->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
    }
}
