<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Teacher\Services\ListMyCourseAssignmentsService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class MyCourseAssignmentRankingRequest extends FormRequest
{
    private ?AttributionCours $resolvedAssignment = null;

    public function authorize(Gate $gate, EstablishmentContext $context, ListMyCourseAssignmentsService $assignments): bool
    {
        $user = $this->user();
        $assignmentId = filter_var($this->route('attribution'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $user instanceof Utilisateur || $assignmentId === false) {
            throw (new ModelNotFoundException)->setModel(AttributionCours::class);
        }

        $this->resolvedAssignment = $assignments->findCurrent($user, $context->id(), $assignmentId);

        return $gate->forUser($user)->allows('viewSlots', $this->resolvedAssignment);
    }

    public function courseAssignment(): AttributionCours
    {
        return $this->resolvedAssignment ?? throw new LogicException('The course assignment was not resolved.');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['periode_id' => ['sometimes', 'integer', 'min:1']];
    }
}
