<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\DTOs\AssignClassTitularData;
use App\Domains\Teacher\DTOs\AssignCourseTeacherData;
use App\Domains\Teacher\Exceptions\CourseAssignmentConflictException;
use App\Models\Classe;
use App\Models\ModeleNode;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveClassTeachingTeamService
{
    public function __construct(
        private AssignClassTitularService $assignTitular,
        private AssignCourseTeacherService $assignCourse,
    ) {}

    /**
     * @param  array{titulaire_id?: int, cours: list<array{node_id: int, enseignant_id: int}>}  $data
     */
    public function save(Classe $class, Utilisateur $actor, array $data): void
    {
        DB::transaction(function () use ($class, $actor, $data): void {
            $class = Classe::query()->where('tenant_id', $class->tenant_id)
                ->whereKey($class->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('assignClassTitular', $class);
            Gate::forUser($actor)->authorize('assignCourseTeacher', $class);

            if ($class->isPrimarySchool()) {
                throw new CourseAssignmentConflictException('Au primaire, seule l’attribution du titulaire de classe est disponible.');
            }

            $year = $class->anneeScolaire()->lockForUpdate()->firstOrFail();

            if (! $class->active || ! $year->active || $year->cloturee) {
                throw new CourseAssignmentConflictException('Cette classe ou cette année scolaire est clôturée. Les changements n’ont pas été enregistrés.');
            }

            $teacherIds = collect($data['cours'])->pluck('enseignant_id')->map(fn ($id): int => (int) $id)->unique();
            $teachers = Utilisateur::query()->where('tenant_id', $class->tenant_id)
                ->where('etablissement_id', $class->etablissement_id)->where('actif', true)
                ->eligibleForCourseTeaching($class->tenant_id)
                ->whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            if ($teachers->count() !== $teacherIds->count()) {
                throw (new ModelNotFoundException)->setModel(Utilisateur::class);
            }
            if (isset($data['titulaire_id'])) {
                Utilisateur::query()->where('tenant_id', $class->tenant_id)
                    ->where('etablissement_id', $class->etablissement_id)->where('actif', true)
                    ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['ENSEIGNANT', 'TITULAIRE'])->validForTenant($class->tenant_id))
                    ->whereKey((int) $data['titulaire_id'])->lockForUpdate()->firstOrFail();
            }

            if (isset($data['titulaire_id']) && $class->titulaire_id !== (int) $data['titulaire_id']) {
                $this->assignTitular->assign($class, $actor, new AssignClassTitularData(
                    titularId: (int) $data['titulaire_id'], startDate: null, endDate: null,
                ));
            }

            foreach ($data['cours'] as $assignment) {
                $course = ModeleNode::query()
                    ->where('tenant_id', $class->tenant_id)
                    ->where('type_node', 'MATIERE')
                    ->whereHas('modeleBulletin', fn ($query) => $query
                        ->where('tenant_id', $class->tenant_id)
                        ->where('classe_id', $class->getKey())
                        ->where('annee_scolaire_id', $class->annee_scolaire_id)
                        ->where('actif', true))
                    ->findOrFail($assignment['node_id']);
                $current = $class->attributionsCours()->where('node_id', $course->id)->where('actif', true)->get();
                $selectedTeacher = $teachers->firstWhere('id', (int) $assignment['enseignant_id']);

                if ($current->count() === 1
                    && $current->first()->enseignant_id === (int) $assignment['enseignant_id']
                    && $selectedTeacher?->hasTenantRole('ENSEIGNANT', $class->tenant_id)) {
                    continue;
                }

                $this->assignCourse->assignCourse($class, $course, $actor, new AssignCourseTeacherData(
                    teacherId: (int) $assignment['enseignant_id'], startDate: null, endDate: null,
                ));
            }
        }, attempts: 3);
    }
}
