<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Domains\Membership\DTOs\CreateInvitationData;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class StoreInvitationRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        return $gate->forUser($user)->allows('create', Invitation::class);
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The invitation actor was not resolved.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:150'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ];
    }

    public function invitationData(): CreateInvitationData
    {
        $email = $this->string('email')->trim()->lower()->toString();

        return new CreateInvitationData(
            email: $email,
            roleId: $this->resolveRole()->getKey(),
        );
    }

    private function resolveRole(): Role
    {
        $role = Role::query()
            ->whereKey($this->integer('role_id'))
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('roles.tenant_id')
                    ->orWhere('roles.tenant_id', $this->user()->tenant_id);
            })
            ->where('roles.code', '!=', 'SUPER_ADMIN')
            ->first();

        return $role
            ?? throw (new ModelNotFoundException)->setModel(Role::class);
    }
}
