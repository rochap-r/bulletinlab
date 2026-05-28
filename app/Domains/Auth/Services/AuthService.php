<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Exceptions\AuthenticationException;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthService implements AuthServiceInterface
{
    public function login(string $telephone, string $password): array
    {
        $user = Utilisateur::where('telephone', $telephone)->first();

        if (!$user || !Hash::check($password, $user->mot_de_passe, [])) {
            throw new AuthenticationException('Invalid credentials');
        }

        if (!$user->actif) {
            throw new AuthenticationException('Account is inactive');
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    public function register(array $data): Utilisateur
    {
        $user = Utilisateur::create([
            'tenant_id' => $data['tenant_id'],
            'etablissement_id' => $data['etablissement_id'] ?? null,
            'nom' => $data['nom'],
            'postnom' => $data['postnom'] ?? null,
            'prenom' => $data['prenom'] ?? null,
            'email' => $data['email'] ?? null,
            'telephone' => $data['telephone'],
            'mot_de_passe' => Hash::make($data['password']),
            'actif' => true,
        ]);

        return $user;
    }

    public function logout(): void
    {
        Auth::user()->tokens()->delete();
    }
}
