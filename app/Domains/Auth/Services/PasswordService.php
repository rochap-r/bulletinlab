<?php

namespace App\Domains\Auth\Services;

use App\Models\AuditLog;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class PasswordService
{
    public function __construct(private Hasher $hasher) {}

    public function change(Utilisateur $user, string $currentPassword, string $newPassword): void
    {
        if (blank($user->telephone_normalise)) {
            throw ValidationException::withMessages([
                'telephone' => ['Le compte doit être régularisé avant de changer le mot de passe.'],
            ]);
        }

        if (! $this->hasher->check($currentPassword, $user->mot_de_passe)) {
            Log::channel('security')->warning('auth.password.failed', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
            ]);

            throw ValidationException::withMessages([
                'mot_de_passe_actuel' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $this->store($user, $newPassword, clearSiblings: true);
    }

    /**
     * Première connexion : le mot de passe temporaire est déjà
     * connu du système, l'utilisateur renseigne uniquement le nouveau.
     * Réservé aux comptes avec `doit_changer_mot_de_passe`.
     */
    public function setInitial(Utilisateur $user, string $newPassword): void
    {
        if (! $user->doit_changer_mot_de_passe) {
            throw ValidationException::withMessages([
                'mot_de_passe' => ['Aucun changement initial requis pour ce compte.'],
            ]);
        }

        $this->store($user, $newPassword, clearSiblings: false);
    }

    private function store(Utilisateur $user, string $newPassword, bool $clearSiblings): void
    {
        $hash = $this->hasher->make($newPassword);

        DB::transaction(function () use ($user, $hash, $clearSiblings): void {
            $siblings = Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $user->telephone_normalise)
                ->lockForUpdate()
                ->get(['id', 'tenant_id']);

            $query = Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $user->telephone_normalise);

            if ($clearSiblings) {
                $query->update(['mot_de_passe' => $hash, 'doit_changer_mot_de_passe' => false]);
            } else {
                $user->forceFill([
                    'mot_de_passe' => $hash,
                    'doit_changer_mot_de_passe' => false,
                ])->save();
            }

            $user->forceFill(['derniere_connexion_at' => now()])->save();

            PersonalAccessToken::query()
                ->where('tokenable_type', (new Utilisateur)->getMorphClass())
                ->whereIn('tokenable_id', $siblings->pluck('id'))
                ->where('id', '!=', $user->currentAccessToken()?->getKey() ?? 0)
                ->delete();

            AuditLog::query()->create([
                'tenant_id' => $user->tenant_id,
                'utilisateur_id' => $user->getKey(),
                'action' => $clearSiblings ? 'MDP_CHANGE_FORCE' : 'MDP_INITIAL_DEFINI',
                'table_cible' => $user->getTable(),
                'ligne_cible_id' => $user->getKey(),
            ]);

            foreach ($siblings as $sibling) {
                if ($sibling->id === $user->id) {
                    continue;
                }

                AuditLog::query()->create([
                    'tenant_id' => $sibling->tenant_id,
                    'utilisateur_id' => $sibling->id,
                    'action' => $clearSiblings ? 'MDP_CHANGE_FORCE' : 'MDP_INITIAL_DEFINI',
                    'table_cible' => $user->getTable(),
                    'ligne_cible_id' => $sibling->id,
                ]);
            }

            Log::channel('security')->info('auth.password.changed', [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
                'spaces' => $siblings->count(),
            ]);
        }, attempts: 3);
    }
}
