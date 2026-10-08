<?php

namespace App\Http\Requests\Api\V1\Teacher;

use App\Domains\Teacher\DTOs\AssignCourseTeacherData;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\Classe;
use App\Models\ModeleNode;
use App\Models\Utilisateur;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\Attributes\FailOnUnknownFields;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

#[FailOnUnknownFields]
final class AssignCourseTeacherRequest extends FormRequest
{
    private ?Classe $resolvedClass = null;

    private ?ModeleNode $resolvedCourse = null;

    public function authorize(
        EstablishmentContext $establishmentContext,
        Gate $gate,
    ): bool {
        $user = $this->user();

        if (! $user instanceof Utilisateur) {
            return false;
        }

        $this->resolvedClass = $this->resolveClass(
            $user,
            $establishmentContext,
        );
        $this->resolvedCourse = $this->resolveCourse($this->resolvedClass);

        return $gate->forUser($user)->allows(
            'assignCourseTeacher',
            $this->resolvedClass,
        );
    }

    public function schoolClass(): Classe
    {
        return $this->resolvedClass
            ?? throw new LogicException('The assignment class was not resolved.');
    }

    public function course(): ModeleNode
    {
        return $this->resolvedCourse
            ?? throw new LogicException('The assignment course was not resolved.');
    }

    public function actor(): Utilisateur
    {
        $user = $this->user();

        return $user instanceof Utilisateur
            ? $user
            : throw new LogicException('The assignment actor was not resolved.');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enseignant_id' => ['required', 'integer', 'min:1'],
            'date_debut' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_fin' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_debut',
            ],
        ];
    }

    public function assignmentData(): AssignCourseTeacherData
    {
        $validated = $this->validated();

        return new AssignCourseTeacherData(
            teacherId: $this->integer('enseignant_id'),
            startDate: $validated['date_debut'] ?? null,
            endDate: $validated['date_fin'] ?? null,
        );
    }

    private function resolveClass(
        Utilisateur $user,
        EstablishmentContext $establishmentContext,
    ): Classe {
        $classId = $this->positiveRouteIdentifier('classe', Classe::class);

        return Classe::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('etablissement_id', $establishmentContext->id())
            ->findOrFail($classId);
    }

    private function resolveCourse(Classe $class): ModeleNode
    {
        $courseId = $this->positiveRouteIdentifier('node', ModeleNode::class);

        return ModeleNode::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('type_node', 'MATIERE')
            ->whereHas(
                'modeleBulletin',
                fn ($query) => $query
                    ->where('tenant_id', $class->tenant_id)
                    ->where('classe_id', $class->getKey())
                    ->where('annee_scolaire_id', $class->annee_scolaire_id)
                    ->where('actif', true),
            )
            ->findOrFail($courseId);
    }

    /**
     * @param  class-string  $model
     */
    private function positiveRouteIdentifier(string $parameter, string $model): int
    {
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
