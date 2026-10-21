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
use Illuminate\Support\Str;
use LogicException;

#[FailOnUnknownFields]
final class MyCourseAssignmentSlotIndexRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    protected function prepareForValidation(): void
    {
        $includeCalculated = $this->query->get('include_calculated');

        if (is_string($includeCalculated)) {
            $normalized = Str::lower($includeCalculated);

            if (in_array($normalized, ['true', 'false'], true)) {
                $this->query->set(
                    'include_calculated',
                    $normalized === 'true' ? '1' : '0',
                );
            }
        }
    }

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
            'include_calculated' => ['sometimes', 'boolean'],
        ];

        foreach (array_diff(array_keys($this->query()), array_keys($rules)) as $unknownField) {
            $rules[(string) $unknownField] = ['prohibited'];
        }

        return $rules;
    }
}
