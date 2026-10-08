<?php

namespace App\Http\Requests\Api\V1\Classroom;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

#[FailOnUnknownFields]
final class DeliberateClassRequest extends FormRequest
{
    private ?Classe $resolvedClasse = null;

    private ?ModelePeriode $resolvedPeriod = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $classeId = $this->positiveRouteInteger(
            'classe',
            Classe::class,
        );
        $periodId = $this->positiveRouteInteger(
            'periode',
            ModelePeriode::class,
        );
        $establishment = $establishmentContext->current();

        $this->resolvedClasse = Classe::query()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('etablissement_id', $establishment->getKey())
            ->findOrFail($classeId);

        if (! $gate->forUser($user)->allows(
            'deliberateClass',
            $this->resolvedClasse,
        )) {
            return false;
        }

        $this->resolvedPeriod = ModelePeriode::query()
            ->where('tenant_id', $establishment->tenant_id)
            ->whereHas(
                'modeleBulletin',
                function ($query) use ($classeId): void {
                    $query->where('classe_id', $classeId);
                },
            )
            ->findOrFail($periodId);

        return true;
    }

    public function classe(): Classe
    {
        return $this->resolvedClasse
            ?? throw new LogicException('The class was not resolved.');
    }

    public function period(): ModelePeriode
    {
        return $this->resolvedPeriod
            ?? throw new LogicException('The period was not resolved.');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'lieu_deliberation' => ['required', 'string', 'max:255'],
            'date_deliberation' => ['required', 'date'],
            'decisions' => ['nullable', 'array'],
            'decisions.*.bulletin_id' => [
                'required_with:decisions',
                'integer',
                'min:1',
            ],
            'decisions.*.decision' => [
                'required_with:decisions',
                'string',
                Rule::in([
                    'PASSE',
                    'DOUBLE',
                    'ECHOUE',
                    'REPECHAGE',
                    'REORIENTE',
                    'DIPLOME',
                ]),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function decisionMap(): array
    {
        $map = [];

        foreach ((array) $this->input('decisions', []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $bulletinId = filter_var(
                $entry['bulletin_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]],
            );
            $decision = $entry['decision'] ?? null;

            if ($bulletinId !== false && is_string($decision)) {
                $map[$bulletinId] = $decision;
            }
        }

        return $map;
    }

    /**
     * @param  class-string  $model
     */
    private function positiveRouteInteger(
        string $parameter,
        string $model,
    ): int {
        $identifier = filter_var(
            $this->route($parameter),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($identifier === false) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        return $identifier;
    }
}
