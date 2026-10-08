<?php

namespace App\Http\Requests\Api\V1\SchoolYear;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\OuvertureAnnuelle;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class CloturerOuvertureAnnuelleRequest extends FormRequest
{
    private ?OuvertureAnnuelle $resolvedOuverture = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $establishment = $establishmentContext->current();

        $ouvertureId = $this->positiveRouteInteger(
            'ouverture_annuelle',
            OuvertureAnnuelle::class,
        );

        $this->resolvedOuverture = OuvertureAnnuelle::query()
            ->whereKey($ouvertureId)
            ->where('etablissement_id', $establishment->getKey())
            ->firstOrFail();

        return $gate->forUser($user)->allows(
            'manageAcademicStructure',
            $establishment,
        );
    }

    public function ouverture(): OuvertureAnnuelle
    {
        return $this->resolvedOuverture
            ?? throw new LogicException('The annual opening was not resolved.');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'motif' => ['nullable', 'string', 'min:1', 'max:1000'],
        ];
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
