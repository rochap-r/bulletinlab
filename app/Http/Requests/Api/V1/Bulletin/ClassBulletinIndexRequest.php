<?php

namespace App\Http\Requests\Api\V1\Bulletin;

use App\Domains\Bulletin\Services\ListBulletinsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

#[FailOnUnknownFields]
final class ClassBulletinIndexRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListBulletinsService $listBulletinsService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $classId = filter_var(
            $this->route('classe'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($classId === false) {
            throw (new ModelNotFoundException)->setModel(Classe::class);
        }

        $this->resolvedClass = $listBulletinsService
            ->findClassForEstablishment(
                $establishmentContext->current(),
                $classId,
            );

        return $gate->forUser($user)->allows(
            'viewClass',
            [Bulletin::class, $this->resolvedClass],
        );
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The class was not resolved.');
    }

    public function decisionStatus(): ?string
    {
        $decisionStatus = $this->validated('decision_statut');

        return is_string($decisionStatus) ? $decisionStatus : null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'decision_statut' => [
                'sometimes',
                'string',
                Rule::in([
                    'EN_COURS',
                    'PASSE',
                    'DOUBLE',
                    'ECHOUE',
                    'REPECHAGE',
                    'REORIENTE',
                    'DIPLOME',
                ]),
            ],
        ];

        foreach (array_keys($this->query()) as $queryField) {
            if (! array_key_exists($queryField, $rules)) {
                $rules[(string) $queryField] = ['prohibited'];
            }
        }

        return $rules;
    }
}
