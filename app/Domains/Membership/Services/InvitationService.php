<?php

namespace App\Domains\Membership\Services;

use App\Domains\Membership\Contracts\InvitationServiceInterface;
use App\Domains\Membership\DTOs\AcceptanceData;
use App\Domains\Membership\DTOs\CreatedInvitationResult;
use App\Domains\Membership\DTOs\CreateInvitationData;
use App\Domains\Membership\Exceptions\InvitationConflictException;
use App\Domains\Membership\Exceptions\InvitationNotFoundException;
use App\Models\AuditLog;
use App\Models\Etablissement;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Utilisateur;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class InvitationService implements InvitationServiceInterface
{
    private const INVITATION_TTL_DAYS = 7;

    public function __construct(private readonly Hasher $hasher) {}

    public function create(
        CreateInvitationData $data,
        Tenant $tenant,
        Etablissement $establishment,
        Utilisateur $actor,
    ): CreatedInvitationResult {
        return DB::transaction(function () use ($data, $tenant, $establishment, $actor): CreatedInvitationResult {
            $activeDuplicate = Invitation::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('etablissement_id', $establishment->getKey())
                ->where('email', $data->email)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where(fn (Builder $query) => $query
                    ->whereNull('expire_at')
                    ->orWhere('expire_at', '>', now()))
                ->lockForUpdate()
                ->first();

            if ($activeDuplicate) {
                throw new InvitationConflictException(
                    'An active invitation already exists for this email in this establishment.'
                );
            }

            $token = bin2hex(random_bytes(32));

            $invitation = Invitation::query()->create([
                'tenant_id' => $tenant->getKey(),
                'etablissement_id' => $establishment->getKey(),
                'email' => $data->email,
                'role_id' => $data->roleId,
                'token_hash' => hash('sha256', $token),
                'expire_at' => now()->addDays(self::INVITATION_TTL_DAYS),
                'created_by' => $actor->getKey(),
            ]);

            $invitation->load('role');

            AuditLog::query()->create([
                'tenant_id' => $tenant->getKey(),
                'utilisateur_id' => $actor->getKey(),
                'action' => 'INVITATION_ENVOYEE',
                'table_cible' => $invitation->getTable(),
                'ligne_cible_id' => $invitation->getKey(),
                'nouvelles_valeurs' => ['email' => $invitation->email, 'role_id' => $data->roleId],
            ]);

            return new CreatedInvitationResult($invitation, $token);
        }, attempts: 3);
    }

    public function revoke(Invitation $invitation, Utilisateur $actor): Invitation
    {
        return DB::transaction(function () use ($invitation, $actor): Invitation {
            $locked = Invitation::query()
                ->whereKey($invitation->getKey())
                ->where('tenant_id', $actor->tenant_id)
                ->where('etablissement_id', $actor->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->revoked_at !== null) {
                $locked->load('role');

                return $locked;
            }

            if ($locked->accepted_at !== null) {
                throw new InvitationConflictException('An accepted invitation cannot be revoked.');
            }

            $locked->update(['revoked_at' => now()]);
            $locked->load('role');

            AuditLog::query()->create([
                'tenant_id' => $locked->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'INVITATION_REVOQUEE',
                'table_cible' => $locked->getTable(),
                'ligne_cible_id' => $locked->getKey(),
                'anciennes_valeurs' => ['email' => $locked->email],
            ]);

            return $locked;
        }, attempts: 3);
    }

    public function accept(AcceptanceData $data): Utilisateur
    {
        return DB::transaction(function () use ($data): Utilisateur {
            $invitation = Invitation::query()
                ->where('token_hash', hash('sha256', $data->token))
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                throw new InvitationNotFoundException('The invitation token is unknown or has been withdrawn.');
            }

            if (! $invitation->isPending()) {
                throw new InvitationConflictException('The invitation is no longer pending.');
            }

            $existingUser = Utilisateur::query()
                ->where('tenant_id', $invitation->tenant_id)
                ->where('email', $invitation->email)
                ->exists();

            if ($existingUser) {
                throw new InvitationConflictException('A membership already exists for this email in this tenant.');
            }

            $role = Role::query()
                ->whereKey($invitation->role_id)
                ->where(fn (Builder $query) => $query
                    ->whereNull('roles.tenant_id')
                    ->orWhere('roles.tenant_id', $invitation->tenant_id))
                ->first();

            if (! $role || $role->code === 'SUPER_ADMIN') {
                throw new InvitationConflictException('The invited role is no longer assignable.');
            }

            $user = Utilisateur::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'etablissement_id' => $invitation->etablissement_id,
                'nom' => $data->nom,
                'postnom' => $data->postnom,
                'prenom' => $data->prenom,
                'email' => $invitation->email,
                'telephone' => $data->telephone,
                'mot_de_passe' => $this->hasher->make($data->motDePasse),
                'actif' => true,
            ]);

            $user->roles()->attach($role->getKey());
            $invitation->update(['accepted_at' => now()]);

            AuditLog::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'utilisateur_id' => $user->getKey(),
                'action' => 'INVITATION_ACCEPTEE',
                'table_cible' => $invitation->getTable(),
                'ligne_cible_id' => $invitation->getKey(),
                'nouvelles_valeurs' => [
                    'role' => $role->code,
                    'etablissement_id' => $invitation->etablissement_id,
                    'utilisateur_id' => $user->getKey(),
                ],
            ]);

            return $user->load('roles');
        }, attempts: 3);
    }
}
