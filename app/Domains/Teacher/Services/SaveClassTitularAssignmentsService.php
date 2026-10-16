<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\DTOs\AssignClassTitularData;
use App\Domains\Teacher\Exceptions\ClassTitularAssignmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SaveClassTitularAssignmentsService
{
    public function __construct(private AssignClassTitularService $assign) {}

    /**
     * @param  list<array{classe_id: int, titulaire_id: int}>  $assignments
     * @return Collection<int, Classe>
     */
    public function save(Etablissement $school, Utilisateur $actor, int $yearId, array $assignments): Collection
    {
        return DB::transaction(function () use ($school, $actor, $yearId, $assignments): Collection {
            $classes = Classe::query()->where('tenant_id', $school->tenant_id)
                ->where('etablissement_id', $school->id)->where('annee_scolaire_id', $yearId)
                ->whereIn('id', array_column($assignments, 'classe_id'))->orderBy('id')->lockForUpdate()->get();
            if ($classes->count() !== count($assignments)) {
                throw (new ModelNotFoundException)->setModel(Classe::class);
            }
            $year = AnneeScolaire::query()->whereKey($yearId)->lockForUpdate()->firstOrFail();
            if (! $year->active || $year->cloturee || $classes->contains(fn (Classe $class): bool => ! $class->active)) {
                throw new ClassTitularAssignmentConflictException('La classe ou l’année scolaire est clôturée. Aucun titulariat n’a été modifié.');
            }
            $teacherIds = collect($assignments)->pluck('titulaire_id')->map(fn ($id): int => (int) $id)->unique();
            $teachers = Utilisateur::query()->where('tenant_id', $school->tenant_id)
                ->where('etablissement_id', $school->id)->where('actif', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.name', ['ENSEIGNANT', 'TITULAIRE'])->validForTenant($school->tenant_id))
                ->whereIn('id', $teacherIds)->orderBy('id')->lockForUpdate()->get();
            if ($teachers->count() !== $teacherIds->count()) {
                throw (new ModelNotFoundException)->setModel(Utilisateur::class);
            }
            $choices = collect($assignments)->keyBy(fn (array $assignment): int => (int) $assignment['classe_id']);
            foreach ($classes as $class) {
                Gate::forUser($actor)->authorize('assignClassTitular', $class);
                $teacherId = (int) $choices[$class->id]['titulaire_id'];
                if ($class->titulaire_id === $teacherId) {
                    continue;
                }
                $this->assign->assign($class, $actor, new AssignClassTitularData(
                    titularId: $teacherId, startDate: null, endDate: null,
                ));
            }

            return $classes->fresh(['cycle', 'niveau', 'option']);
        }, attempts: 3);
    }
}
