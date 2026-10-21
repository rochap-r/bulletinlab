<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Contracts\AssignCourseTeacherServiceInterface;
use App\Domains\Teacher\DTOs\AssignCourseTeacherData;
use App\Domains\Teacher\Events\CourseTeacherAssigned;
use App\Domains\Teacher\Exceptions\CourseAssignmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\AttributionCours;
use App\Models\AuditLog;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleMaximum;
use App\Models\ModeleNode;
use App\Models\Role;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AssignCourseTeacherService implements AssignCourseTeacherServiceInterface
{
    public function assignCourse(
        Classe $class,
        ModeleNode $course,
        Utilisateur $actor,
        AssignCourseTeacherData $data,
    ): AttributionCours {
        return DB::transaction(function () use ($class, $course, $actor, $data): AttributionCours {
            $lockedClass = Classe::query()
                ->whereKey($class->getKey())
                ->where('tenant_id', $class->tenant_id)
                ->where('etablissement_id', $class->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize('assignCourseTeacher', $lockedClass);

            if (! $lockedClass->active) {
                throw new CourseAssignmentConflictException(
                    'Teachers cannot be assigned to an archived class.',
                );
            }

            $schoolYear = AnneeScolaire::withoutGlobalScopes()
                ->whereKey($lockedClass->annee_scolaire_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $schoolYear->active || $schoolYear->cloturee) {
                throw new CourseAssignmentConflictException(
                    'Teachers cannot be assigned in a closed school year.',
                );
            }

            $model = ModeleBulletin::query()
                ->whereKey($course->modele_bulletin_id)
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->where('annee_scolaire_id', $schoolYear->getKey())
                ->where('actif', true)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCourse = ModeleNode::query()
                ->whereKey($course->getKey())
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('modele_bulletin_id', $model->getKey())
                ->where('type_node', 'MATIERE')
                ->lockForUpdate()
                ->firstOrFail();

            $configuredMaximum = ModeleMaximum::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('modele_bulletin_id', $model->getKey())
                ->where('node_id', $lockedCourse->getKey())
                ->where('maxima', '>', 0)
                ->lockForUpdate()
                ->first();

            if (! $configuredMaximum) {
                throw new CourseAssignmentConflictException(
                    'The course has no configured grading maximum.',
                );
            }

            $teacher = $this->resolveTeacher(
                $lockedClass,
                $data->teacherId,
            );
            $this->ensureTeacherRole($teacher, $actor, $lockedClass);
            [$startDate, $endDate] = $this->resolveAssignmentDates(
                $schoolYear,
                $data,
            );

            $assignments = AttributionCours::query()
                ->where('tenant_id', $lockedClass->tenant_id)
                ->where('classe_id', $lockedClass->getKey())
                ->where('modele_bulletin_id', $model->getKey())
                ->where('node_id', $lockedCourse->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $targetAssignment = $assignments->firstWhere(
                'enseignant_id',
                $teacher->getKey(),
            );
            $activeAssignments = $assignments->where('actif', true);
            $targetPreviousValues = $targetAssignment
                ? [
                    'enseignant_id' => $targetAssignment->enseignant_id,
                    'attribution_id' => $targetAssignment->getKey(),
                    'date_debut' => $targetAssignment->date_debut?->toDateString(),
                    'date_fin' => $targetAssignment->date_fin?->toDateString(),
                    'actif' => $targetAssignment->actif,
                ]
                : [];

            if ($targetAssignment
                && $targetAssignment->actif
                && $activeAssignments->count() === 1
                && $targetAssignment->date_debut?->toDateString() === $startDate
                && $targetAssignment->date_fin?->toDateString() === $endDate) {
                return $targetAssignment->load(['enseignant', 'node']);
            }

            $previousAssignment = $activeAssignments
                ->first(fn (AttributionCours $assignment): bool => (
                    $assignment->enseignant_id !== $teacher->getKey()
                ));
            $previousAssignmentValues = $previousAssignment
                ? [
                    'enseignant_id' => $previousAssignment->enseignant_id,
                    'attribution_id' => $previousAssignment->getKey(),
                    'date_debut' => $previousAssignment->date_debut?->toDateString(),
                    'date_fin' => $previousAssignment->date_fin?->toDateString(),
                    'actif' => $previousAssignment->actif,
                ]
                : $targetPreviousValues;
            $deactivatedAssignmentIds = [];

            foreach ($activeAssignments as $activeAssignment) {
                if ($activeAssignment->enseignant_id === $teacher->getKey()) {
                    continue;
                }

                $previousStartDate = $activeAssignment->date_debut?->toDateString();
                $previousEndDate = $previousStartDate !== null
                    && $previousStartDate > $startDate
                    ? $previousStartDate
                    : $startDate;

                $activeAssignment->update([
                    'date_fin' => $previousEndDate,
                    'actif' => false,
                ]);
                $deactivatedAssignmentIds[] = $activeAssignment->getKey();
            }

            if ($targetAssignment) {
                $targetAssignment->update([
                    'date_debut' => $startDate,
                    'date_fin' => $endDate,
                    'actif' => true,
                ]);
            } else {
                $targetAssignment = AttributionCours::query()->create([
                    'tenant_id' => $lockedClass->tenant_id,
                    'classe_id' => $lockedClass->getKey(),
                    'modele_bulletin_id' => $model->getKey(),
                    'node_id' => $lockedCourse->getKey(),
                    'enseignant_id' => $teacher->getKey(),
                    'date_debut' => $startDate,
                    'date_fin' => $endDate,
                    'actif' => true,
                ]);
            }

            AuditLog::query()->create([
                'tenant_id' => $lockedClass->tenant_id,
                'utilisateur_id' => $actor->getKey(),
                'action' => 'ATTRIBUTION_COURS_ENREGISTREE',
                'table_cible' => $targetAssignment->getTable(),
                'ligne_cible_id' => $targetAssignment->getKey(),
                'anciennes_valeurs' => [
                    ...$previousAssignmentValues,
                    'attributions_desactivees' => $deactivatedAssignmentIds,
                ],
                'nouvelles_valeurs' => [
                    'classe_id' => $lockedClass->getKey(),
                    'node_id' => $lockedCourse->getKey(),
                    'enseignant_id' => $teacher->getKey(),
                    'date_debut' => $startDate,
                    'date_fin' => $endDate,
                    'actif' => true,
                ],
            ]);

            CourseTeacherAssigned::dispatch(
                tenantId: $lockedClass->tenant_id,
                establishmentId: $lockedClass->etablissement_id,
                schoolYearId: $schoolYear->getKey(),
                classId: $lockedClass->getKey(),
                courseNodeId: $lockedCourse->getKey(),
                teacherId: $teacher->getKey(),
                previousTeacherId: $previousAssignment?->enseignant_id,
                actorId: $actor->getKey(),
            );

            return $targetAssignment->refresh()->load(['enseignant', 'node']);
        }, attempts: 3);
    }

    private function resolveTeacher(Classe $class, int $teacherId): Utilisateur
    {
        $teacher = Utilisateur::query()
            ->whereKey($teacherId)
            ->where('tenant_id', $class->tenant_id)
            ->where('etablissement_id', $class->etablissement_id)
            ->where('actif', true)
            ->eligibleForCourseTeaching($class->tenant_id)
            ->lockForUpdate()
            ->first();

        if (! $teacher) {
            throw (new ModelNotFoundException)->setModel(
                Utilisateur::class,
                [$teacherId],
            );
        }

        return $teacher;
    }

    private function ensureTeacherRole(
        Utilisateur $teacher,
        Utilisateur $actor,
        Classe $class,
    ): void {
        if ($teacher->hasTenantRole('ENSEIGNANT', $class->tenant_id)) {
            return;
        }

        $teacherRole = Role::query()
            ->where(function (Builder $query): void {
                $query->where('roles.name', 'ENSEIGNANT')
                    ->orWhere('roles.code', 'ENSEIGNANT');
            })
            ->validForTenant($class->tenant_id)
            ->orderByRaw('tenant_id IS NULL DESC')
            ->firstOrFail();

        $teacher->assignRole($teacherRole);
        $teacher->unsetRelation('roles');

        AuditLog::query()->create([
            'tenant_id' => $class->tenant_id,
            'utilisateur_id' => $actor->getKey(),
            'action' => 'ROLE_ENSEIGNANT_CONFERE_PAR_ATTRIBUTION',
            'table_cible' => $teacher->getTable(),
            'ligne_cible_id' => $teacher->getKey(),
            'nouvelles_valeurs' => [
                'role' => 'ENSEIGNANT',
                'classe_id' => $class->getKey(),
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function resolveAssignmentDates(
        AnneeScolaire $schoolYear,
        AssignCourseTeacherData $data,
    ): array {
        $schoolYearStart = CarbonImmutable::parse(
            (string) $schoolYear->date_debut,
        )->startOfDay();
        $schoolYearEnd = CarbonImmutable::parse(
            (string) $schoolYear->date_fin,
        )->startOfDay();
        $startDate = $data->startDate !== null
            ? CarbonImmutable::parse($data->startDate)->startOfDay()
            : $schoolYearStart;
        $endDate = $data->endDate !== null
            ? CarbonImmutable::parse($data->endDate)->startOfDay()
            : null;

        if ($startDate->lt($schoolYearStart)
            || $startDate->gt($schoolYearEnd)
            || ($endDate && (
                $endDate->lt($startDate)
                || $endDate->gt($schoolYearEnd)
            ))) {
            throw new CourseAssignmentConflictException(
                'Assignment dates must fall within the school year.',
            );
        }

        return [
            $startDate->toDateString(),
            $endDate?->toDateString(),
        ];
    }
}
