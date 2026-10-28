<?php

namespace App\Domains\Responsable\Services;

use App\Domains\Responsable\Contracts\ResponsableAuthServiceInterface;
use App\Domains\Responsable\DTOs\ResponsableAuthenticatedSession;
use App\Domains\Responsable\Exceptions\ResponsableAuthenticationException;
use App\Models\AuditLog;
use App\Models\Responsable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

final readonly class ResponsableAuthService implements ResponsableAuthServiceInterface
{
    private const DUMMY_PASSWORD_HASH = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private Hasher $hasher,
        private ResponsableStudentLinkSyncService $linkSyncService,
        private ResponsableMonitoringService $monitoringService,
    ) {}

    public function login(string $telephone, string $password): ResponsableAuthenticatedSession
    {
        $responsable = $this->linkSyncService->syncForPhone($telephone);
        $passwordMatches = $this->hasher->check(
            $password,
            $responsable?->mot_de_passe ?? self::DUMMY_PASSWORD_HASH,
        );

        if (! $responsable || ! $responsable->actif || ! $passwordMatches) {
            $this->monitoringService->recordAuthentication(false);
            Log::channel('security')->warning('responsable.auth.login.failed');

            throw new ResponsableAuthenticationException('Invalid credentials');
        }

        $responsable->forceFill([
            'derniere_connexion_at' => now(),
        ])->save();

        Log::channel('security')->info('responsable.auth.login', [
            'responsable_id' => $responsable->getKey(),
        ]);
        $this->monitoringService->recordAuthentication(true, $responsable);

        return $this->issueSession($responsable);
    }

    public function setInitialPassword(
        Responsable $responsable,
        string $password,
    ): ResponsableAuthenticatedSession {
        if (! $responsable->doit_changer_mot_de_passe) {
            throw ValidationException::withMessages([
                'mot_de_passe' => ['Aucun changement initial requis pour ce compte.'],
            ]);
        }

        if ($this->hasher->check($password, $responsable->mot_de_passe)) {
            throw ValidationException::withMessages([
                'mot_de_passe' => ['Le nouveau mot de passe doit être différent du mot de passe actuel.'],
            ]);
        }

        DB::transaction(function () use ($responsable, $password): void {
            $lockedResponsable = Responsable::query()
                ->whereKey($responsable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedResponsable->forceFill([
                'mot_de_passe' => $this->hasher->make($password),
                'doit_changer_mot_de_passe' => false,
                'derniere_connexion_at' => now(),
            ])->save();

            $lockedResponsable->tokens()->delete();

            AuditLog::query()->create([
                'responsable_id' => $lockedResponsable->getKey(),
                'action' => 'RESPONSABLE_MDP_INITIAL_DEFINI',
                'table_cible' => $lockedResponsable->getTable(),
                'ligne_cible_id' => $lockedResponsable->getKey(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::channel('security')->info('responsable.auth.password.initial.changed', [
                'responsable_id' => $lockedResponsable->getKey(),
            ]);
        }, attempts: 3);

        return $this->issueSession($responsable->refresh());
    }

    public function logout(Responsable $responsable): void
    {
        $accessToken = $responsable->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }
    }

    public function logoutAll(Responsable $responsable): void
    {
        $responsable->tokens()->delete();
    }

    private function issueSession(Responsable $responsable): ResponsableAuthenticatedSession
    {
        $abilities = ['context:responsable'];
        $abilities[] = $responsable->doit_changer_mot_de_passe
            ? 'responsable:password-change'
            : 'responsable:read';

        $accessToken = $responsable
            ->createToken(
                'responsable-auth',
                $abilities,
                now()->addMinutes((int) config('responsables.session_minutes', 720)),
            )
            ->plainTextToken;

        return new ResponsableAuthenticatedSession($responsable, $accessToken);
    }
}
