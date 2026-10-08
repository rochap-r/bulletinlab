<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Teacher\Services\ListMyCourseAssignmentsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class MyCourseAssignmentSlotIndexRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListMyCourseAssignmentsService $listAssignmentsService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur
            || ! $gate->forUser($user)->allows(
                'viewAny',
                AttributionCours::class,
            )) {
            return false;
        }

        $assignmentId = filter_var(
            $this->route('attribution'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($assignmentId === false) {
            throw (new ModelNotFoundException)->setModel(
                AttributionCours::class,
            );
        }

        $this->resolvedAssignment = $listAssignmentsService->findCurrent(
            $user,
            $establishmentContext->id(),
            $assignmentId,
        );

        return $gate->forUser($user)->allows(
            'viewSlots',
            $this->resolvedAssignment,
        );
    }

    public function courseAssignment(): AttributionCours
    {
        return $this->resolvedAssignment
            ?? throw new LogicException(
                'The course assignment was not resolved.',
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
}
