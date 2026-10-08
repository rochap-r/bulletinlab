<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Domains\Teacher\Services\ListMyCourseAssignmentSlotsService;
use App\Domains\Teacher\Services\ListMyCourseAssignmentsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\Inscription;
use App\Models\ModeleMaximum;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

#[FailOnUnknownFields]
final class UpsertMyCourseAssignmentNoteRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    private ?ModeleMaximum $resolvedSlot = null;

    private ?int $resolvedEnrollmentId = null;

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
        $this->resolvedEnrollmentId = $this->positiveRouteId(
            'inscription',
            Inscription::class,
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
            'upsertNote',
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

    public function enrollmentId(): int
    {
        return $this->resolvedEnrollmentId
            ?? throw new LogicException(
                'The enrollment was not resolved.',
            );
    }

    public function teacher(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException(
                'The authenticated teacher was not resolved.',
            );
    }

    public function resultStatus(): NoteResultStatus
    {
        return NoteResultStatus::from(
            (string) $this->validated(
                'statut_resultat',
                NoteResultStatus::Saisie->value,
            ),
        );
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maximum = $this->entrySlot()->maxima;
        $status = (string) $this->input(
            'statut_resultat',
            NoteResultStatus::Saisie->value,
        );
        $rules = [
            'statut_resultat' => [
                'sometimes',
                Rule::in(NoteResultStatus::teacherEditableValues()),
            ],
            'valeur' => [
                Rule::requiredIf(
                    $status === NoteResultStatus::Saisie->value,
                ),
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                "max:{$maximum}",
            ],
            'commentaire' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'motif_correction' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
            'lock_version' => [
                'sometimes',
                'integer:strict',
                'min:1',
                'max:4294967294',
            ],
        ];

        foreach (array_keys($this->query()) as $queryField) {
            $field = (string) $queryField;
            $rules[$field] = [
                ...($rules[$field] ?? []),
                'prohibited',
            ];
        }

        return $rules;
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $status = (string) $this->input(
                    'statut_resultat',
                    NoteResultStatus::Saisie->value,
                );

                if ($status !== NoteResultStatus::Saisie->value
                    && $this->input('valeur') !== null) {
                    $validator->errors()->add(
                        'valeur',
                        'A grade value is only allowed for a recorded result.',
                    );
                }
            },
        ];
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
