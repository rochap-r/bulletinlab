<?php

namespace App\Http\Requests\Api\V1\Bulletin;

use App\Domains\Bulletin\Services\ListBulletinsService;
use App\Domains\Bulletin\Services\PreviewBulletinCalculationService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Bulletin;
use App\Models\ModelePeriode;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class PublishBulletinCalculationRequest extends FormRequest
{
    private ?Bulletin $resolvedBulletin = null;

    private ?ModelePeriode $resolvedPeriod = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListBulletinsService $listBulletinsService,
        PreviewBulletinCalculationService $previewService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $bulletinId = $this->positiveRouteInteger(
            'bulletin',
            Bulletin::class,
        );
        $periodId = $this->positiveRouteInteger(
            'periode',
            ModelePeriode::class,
        );
        $establishment = $establishmentContext->current();
        $this->resolvedBulletin = $listBulletinsService
            ->findForEstablishment($establishment, $bulletinId);

        if (! $gate->forUser($user)->allows(
            'publishCalculation',
            $this->resolvedBulletin,
        )) {
            return false;
        }

        $this->resolvedPeriod = $previewService->findPeriodForBulletin(
            $establishment,
            $this->resolvedBulletin,
            $periodId,
        );

        return true;
    }

    public function bulletin(): Bulletin
    {
        return $this->resolvedBulletin
            ?? throw new LogicException('The report card was not resolved.');
    }

    public function period(): ModelePeriode
    {
        return $this->resolvedPeriod
            ?? throw new LogicException('The report card period was not resolved.');
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
