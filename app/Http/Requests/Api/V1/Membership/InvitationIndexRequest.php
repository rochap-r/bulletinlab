<?php

namespace App\Http\Requests\Api\V1\Membership;

use App\Models\Invitation;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class InvitationIndexRequest extends FormRequest
{
    public function authorize(Gate $gate): bool
    {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        return $gate->forUser($user)->allows('viewAny', Invitation::class);
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The invitation scoping actor was not resolved.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
