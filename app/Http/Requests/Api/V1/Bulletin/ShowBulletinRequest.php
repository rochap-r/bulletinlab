<?php

namespace App\Http\Requests\Api\V1\Bulletin;

use App\Domains\Bulletin\Services\ListBulletinsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Bulletin;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class ShowBulletinRequest extends FormRequest
{
    private ?Bulletin $resolvedBulletin = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListBulletinsService $listBulletinsService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $bulletinId = filter_var(
            $this->route('bulletin'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($bulletinId === false) {
            throw (new ModelNotFoundException)->setModel(Bulletin::class);
        }

        $this->resolvedBulletin = $listBulletinsService
            ->findForEstablishment(
                $establishmentContext->current(),
                $bulletinId,
            );

        return $gate->forUser($user)->allows(
            'view',
            $this->resolvedBulletin,
        );
    }

    public function bulletin(): Bulletin
    {
        return $this->resolvedBulletin
            ?? throw new LogicException('The report card was not resolved.');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [];

        foreach (array_keys($this->query()) as $queryField) {
            $rules[(string) $queryField] = ['prohibited'];
        }

        return $rules;
    }
}
