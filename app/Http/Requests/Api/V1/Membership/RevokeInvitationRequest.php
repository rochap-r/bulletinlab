<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Invitation;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class RevokeInvitationRequest extends FormRequest
{
    private ?Invitation $resolvedInvitation = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $this->resolvedInvitation = $this->resolveInvitation(
            $user,
            $establishmentContext,
        );

        return $gate->forUser($user)->allows(
            'revoke',
            $this->resolvedInvitation,
        );
    }

    public function invitation(): Invitation
    {
        return $this->resolvedInvitation
            ?? throw new LogicException('The invitation was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The revoke actor was not resolved.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    private function resolveInvitation(
        Utilisateur $user,
        EstablishmentContext $establishmentContext,
    ): Invitation {
        $invitationId = filter_var(
            $this->route('invitation'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($invitationId === false) {
            throw (new ModelNotFoundException)->setModel(Invitation::class);
        }

        return Invitation::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($invitationId);
    }
}
