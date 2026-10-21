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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use LogicException;

#[FailOnUnknownFields]
final class ImportMyCourseAssignmentGradeMatrixRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    /** @var Collection<int, ModeleMaximum>|null */
    private ?Collection $resolvedColumns = null;

    public function authorize(
        Gate $gate,
        EstablishmentContext $establishmentContext,
        ListMyCourseAssignmentsService $listAssignmentsService,
        ListMyCourseAssignmentSlotsService $listSlotsService,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur
            || ! $gate->forUser($user)->allows('viewAny', AttributionCours::class)) {
            return false;
        }

        $assignmentId = filter_var(
            $this->route('attribution'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($assignmentId === false) {
            throw (new ModelNotFoundException)->setModel(AttributionCours::class);
        }

        $this->resolvedAssignment = $listAssignmentsService->findCurrent(
            $user,
            $establishmentContext->id(),
            $assignmentId,
        );
        $this->resolvedColumns = $listSlotsService->matrixColumns(
            $this->resolvedAssignment,
        );

        return $gate->forUser($user)->allows('viewSlots', $this->resolvedAssignment)
            && $this->resolvedColumns
                ->filter(fn (ModeleMaximum $column): bool => (bool) $column->periode->saisissable)
                ->every(fn (ModeleMaximum $column): bool => $gate->forUser($user)->allows(
                    'importNotes',
                    [$this->resolvedAssignment, $column],
                ));
    }

    public function courseAssignment(): AttributionCours
    {
        return $this->resolvedAssignment
            ?? throw new LogicException('The course assignment was not resolved.');
    }

    /** @return Collection<int, ModeleMaximum> */
    public function matrixColumns(): Collection
    {
        return $this->resolvedColumns
            ?? throw new LogicException('The grade matrix columns were not resolved.');
    }

    public function teacher(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The authenticated teacher was not resolved.');
    }

    public function spreadsheet(): UploadedFile
    {
        $file = $this->file('file');

        return $file instanceof UploadedFile
            ? $file
            : throw new LogicException('The validated note spreadsheet was not resolved.');
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = [
            'file' => [
                'required',
                File::types(['csv', 'xlsx'])->max('2mb'),
                'extensions:csv,xlsx',
            ],
            'motif_correction' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];

        foreach (array_keys($this->query()) as $queryField) {
            $field = (string) $queryField;
            $rules[$field] = [...($rules[$field] ?? []), 'prohibited'];
        }

        return $rules;
    }
}
