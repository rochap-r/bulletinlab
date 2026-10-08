<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Teacher\Services\ListMyCourseAssignmentSlotsService;
use App\Domains\Teacher\Services\ListMyCourseAssignmentsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\ModeleMaximum;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class MyCourseAssignmentGradeSheetRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    private ?ModeleMaximum $resolvedSlot = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListMyCourseAssignmentsService $listAssignmentsService,
        ListMyCourseAssignmentSlotsService $listSlotsService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur
            || ! $gate->forUser($user)->allows(
                'viewAny',
                AttributionCours::class,
            )) {
            return false;
        }

        $assignmentId = $this->positiveRouteId(
            'attribution',
            AttributionCours::class,
        );
        $slotId = $this->positiveRouteId(
            'creneau',
            ModeleMaximum::class,
        );
        $this->resolvedAssignment = $listAssignmentsService->findCurrent(
            $user,
            $establishmentContext->id(),
            $assignmentId,
        );
        $this->resolvedSlot = $listSlotsService->findEligible(
            $this->resolvedAssignment,
            $slotId,
        );

        return $gate->forUser($user)->allows(
            'viewGradeSheet',
            [
                $this->resolvedAssignment,
                $this->resolvedSlot,
            ],
        );
    }

    public function courseAssignment(): AttributionCours
    {
        return $this->resolvedAssignment
            ?? throw new LogicException(
                'The course assignment was not resolved.',
            );
    }

    public function entrySlot(): ModeleMaximum
    {
        return $this->resolvedSlot
            ?? throw new LogicException(
                'The grade entry slot was not resolved.',
            );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];

        foreach (array_diff(array_keys($this->query()), array_keys($rules)) as $unknownField) {
            $rules[(string) $unknownField] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function positiveRouteId(
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
