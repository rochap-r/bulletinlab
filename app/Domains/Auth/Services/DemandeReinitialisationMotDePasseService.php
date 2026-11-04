<?php

namespace App\Domains\Auth\Services;

use App\Models\AuditLog;
use App\Models\DemandeReinitialisationMotDePasse;
use App\Models\Utilisateur;
use App\Models\UtilisateurPlateforme;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class DemandeReinitialisationMotDePasseService
{
    public const MOT_DE_PASSE_TEMPORAIRE = '123456';

    private const ROLES_ADMINISTRATEUR = ['ADMIN_ECOLE', 'ADMIN_TENANT'];

    public function __construct(private Hasher $hasher) {}

    public function demander(
        string $telephone,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        try {
            $telephoneNormalise = PhoneNumberNormalizer::normalize($telephone);
        } catch (\InvalidArgumentException) {
            return;
        }

        $utilisateurs = Utilisateur::withoutGlobalScopes()
            ->where('telephone_normalise', $telephoneNormalise)
            ->where('actif', true)
            ->orderBy('id')
            ->get();

        $demandesCreees = 0;

        foreach ($utilisateurs as $utilisateur) {
            $demandeCreee = DB::transaction(function () use ($utilisateur, $ipAddress, $userAgent): bool {
                $verrouille = Utilisateur::withoutGlobalScopes()
                    ->lockForUpdate()
                    ->find($utilisateur->getKey());

                if ($verrouille === null || ! $verrouille->actif) {
                    return false;
                }

                if (DemandeReinitialisationMotDePasse::query()
                    ->whereBelongsTo($verrouille)
                    ->enAttente()
                    ->exists()) {
                    return false;
                }

                DemandeReinitialisationMotDePasse::query()->create([
                    'utilisateur_id' => $verrouille->getKey(),
                    'tenant_id' => $verrouille->tenant_id,
                    'destinataire' => $this->estAdministrateurEcole($verrouille)
                        ? DemandeReinitialisationMotDePasse::DESTINATAIRE_PLATEFORME
                        : DemandeReinitialisationMotDePasse::DESTINATAIRE_ECOLE,
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                ]);

                return true;
            }, attempts: 3);

            $demandesCreees += (int) $demandeCreee;
        }

        Log::channel('security')->info('auth.password_reset.requested', [
            'matched_administrator_accounts' => $utilisateurs
                ->filter(fn (Utilisateur $utilisateur): bool => $this->estAdministrateurEcole($utilisateur))
                ->count(),
            'requests_created' => $demandesCreees,
        ]);
    }

    public function reinitialiser(
        DemandeReinitialisationMotDePasse $demande,
        UtilisateurPlateforme $acteur,
    ): DemandeReinitialisationMotDePasse {
        return DB::transaction(function () use ($demande, $acteur): DemandeReinitialisationMotDePasse {
            $verrouillee = DemandeReinitialisationMotDePasse::query()
                ->lockForUpdate()
                ->findOrFail($demande->getKey());

            abort_unless($verrouillee->destinataire === DemandeReinitialisationMotDePasse::DESTINATAIRE_PLATEFORME, 404);

            if ($verrouillee->statut === DemandeReinitialisationMotDePasse::STATUT_TRAITEE) {
                return $this->charger($verrouillee);
            }

            $utilisateur = Utilisateur::withoutGlobalScopes()
                ->lockForUpdate()
                ->findOrFail($verrouillee->utilisateur_id);

            if (! $utilisateur->actif || ! $this->estAdministrateurEcole($utilisateur)) {
                throw ValidationException::withMessages([
                    'demande' => ['Ce compte n’est plus un administrateur d’école actif.'],
                ]);
            }

            $utilisateur->forceFill([
                'mot_de_passe' => $this->hasher->make(self::MOT_DE_PASSE_TEMPORAIRE),
                'doit_changer_mot_de_passe' => true,
                'derniere_connexion_at' => null,
            ])->save();
            $utilisateur->tokens()->delete();

            $verrouillee->forceFill([
                'statut' => DemandeReinitialisationMotDePasse::STATUT_TRAITEE,
                'traitee_par_id' => $acteur->getKey(),
                'traitee_at' => now(),
            ])->save();

            AuditLog::query()->create([
                'tenant_id' => $utilisateur->tenant_id,
                'utilisateur_id' => null,
                'action' => 'MDP_ADMIN_REINITIALISE_PAR_PLATEFORME',
                'table_cible' => $utilisateur->getTable(),
                'ligne_cible_id' => $utilisateur->getKey(),
                'nouvelles_valeurs' => [
                    'demande_id' => $verrouillee->getKey(),
                    'traitee_par_id' => $acteur->getKey(),
                    'doit_changer_mot_de_passe' => true,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::channel('security')->warning('auth.password_reset.completed', [
                'tenant_id' => $utilisateur->tenant_id,
                'user_id' => $utilisateur->getKey(),
                'by_platform_user_id' => $acteur->getKey(),
            ]);

            return $this->charger($verrouillee);
        }, attempts: 3);
    }

    public function reinitialiserPourEcole(
        DemandeReinitialisationMotDePasse $demande,
        Utilisateur $acteur,
    ): DemandeReinitialisationMotDePasse {
        return DB::transaction(function () use ($demande, $acteur): DemandeReinitialisationMotDePasse {
            $verrouillee = DemandeReinitialisationMotDePasse::query()
                ->lockForUpdate()
                ->findOrFail($demande->getKey());

            abort_unless(
                $verrouillee->destinataire === DemandeReinitialisationMotDePasse::DESTINATAIRE_ECOLE
                && (int) $verrouillee->tenant_id === (int) $acteur->tenant_id,
                404,
            );

            if ($verrouillee->statut === DemandeReinitialisationMotDePasse::STATUT_TRAITEE) {
                return $this->charger($verrouillee);
            }

            $cible = Utilisateur::withoutGlobalScopes()->lockForUpdate()->findOrFail($verrouillee->utilisateur_id);
            if (! $cible->actif || $this->estAdministrateurEcole($cible)) {
                throw ValidationException::withMessages([
                    'demande' => ['Ce compte ne peut plus être réinitialisé depuis une école.'],
                ]);
            }

            $comptes = Utilisateur::withoutGlobalScopes()
                ->where('telephone_normalise', $cible->telephone_normalise)
                ->where('actif', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->reject(fn (Utilisateur $utilisateur): bool => $this->estAdministrateurEcole($utilisateur));

            $hash = $this->hasher->make(self::MOT_DE_PASSE_TEMPORAIRE);
            foreach ($comptes as $compte) {
                $compte->forceFill([
                    'mot_de_passe' => $hash,
                    'doit_changer_mot_de_passe' => true,
                    'derniere_connexion_at' => null,
                ])->save();
                $compte->tokens()->delete();
            }

            DemandeReinitialisationMotDePasse::query()
                ->whereIn('utilisateur_id', $comptes->modelKeys())
                ->where('destinataire', DemandeReinitialisationMotDePasse::DESTINATAIRE_ECOLE)
                ->enAttente()
                ->update([
                    'statut' => DemandeReinitialisationMotDePasse::STATUT_TRAITEE,
                    'traitee_par_utilisateur_id' => $acteur->getKey(),
                    'traitee_at' => now(),
                ]);

            AuditLog::query()->create([
                'tenant_id' => $acteur->tenant_id,
                'utilisateur_id' => $acteur->getKey(),
                'action' => 'MDP_REINITIALISE_SUR_DEMANDE',
                'table_cible' => $cible->getTable(),
                'ligne_cible_id' => $cible->getKey(),
                'nouvelles_valeurs' => [
                    'demande_id' => $verrouillee->getKey(),
                    'comptes_reinitialises' => $comptes->count(),
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $this->charger($verrouillee->fresh());
        }, attempts: 3);
    }

    private function estAdministrateurEcole(Utilisateur $utilisateur): bool
    {
        return $utilisateur->hasRoleInOwnTenant(self::ROLES_ADMINISTRATEUR);
    }

    private function charger(
        DemandeReinitialisationMotDePasse $demande,
    ): DemandeReinitialisationMotDePasse {
        return $demande->load(['utilisateur', 'tenant', 'traiteePar']);
    }
}
