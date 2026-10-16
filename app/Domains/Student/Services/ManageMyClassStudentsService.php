<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\DTOs\EnrollmentData;
use App\Domains\Student\DTOs\EnrollmentExitData;
use App\Domains\Student\Exceptions\EnrollmentConflictException;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ManageMyClassStudentsService
{
    public function __construct(private EnrollmentService $enrollments, private ExitEnrollmentService $exits) {}

    /** @param array<string, mixed> $data */
    public function duplicate(Classe $class, array $data, ?int $except = null): bool
    {
        return Eleve::query()->where('tenant_id', $class->tenant_id)
            ->where('etablissement_id', $class->etablissement_id)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except))
            ->where(function ($query) use ($data): void {
                $query->where(function ($identity) use ($data): void {
                    foreach (['nom', 'postnom', 'prenom', 'date_naissance'] as $field) {
                        $identity->where($field, $data[$field] ?? null);
                    }
                });
                if (filled($data['num_permanent'] ?? null)) {
                    $query->orWhere('num_permanent', $data['num_permanent']);
                }
            })->exists();
    }

    public function assertOpen(Classe $class): void
    {
        $year = AnneeScolaire::query()->whereKey($class->annee_scolaire_id)->lockForUpdate()->firstOrFail();
        $today = today(config('app.business_timezone'))->toDateString();
        if (! $class->active || ! $year->active || $year->cloturee || $today < $year->date_debut || $today > $year->date_fin) {
            throw new EnrollmentConflictException('Cette classe ou son année scolaire est fermée.');
        }
    }

    /** @param list<array<string, mixed>> $rows
     * @return list<Inscription>
     */
    public function create(Classe $class, Utilisateur $actor, array $rows): array
    {
        return DB::transaction(function () use ($class, $actor, $rows): array {
            $class = $this->lockClass($class, $actor);
            $result = [];
            foreach ($rows as $index => $row) {
                if ($this->duplicate($class, $row)) {
                    throw ValidationException::withMessages(["eleves.$index.nom" => 'Un élève avec cette identité ou ce numéro existe déjà dans l’école. Contactez la direction pour vérifier son dossier.']);
                }
                $student = Eleve::query()->create([...$row, 'tenant_id' => $class->tenant_id, 'etablissement_id' => $class->etablissement_id]);
                $result[] = $this->enrollments->enroll($class, new EnrollmentData($student->id, null, today(config('app.business_timezone'))->toDateString()));
            }

            return $result;
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Classe $class, Utilisateur $actor, int $id, array $data): Inscription
    {
        return DB::transaction(function () use ($class, $actor, $id, $data): Inscription {
            $class = $this->lockClass($class, $actor);
            $enrollment = $this->enrollment($class, $id);
            if ($enrollment->statut !== 'ACTIF' || ! $enrollment->eleve->actif) {
                throw new EnrollmentConflictException('Ce dossier est retiré ou archivé et ne peut plus être modifié.');
            }
            if ($this->duplicate($class, [...$enrollment->eleve->getAttributes(), ...$data], $enrollment->eleve_id)) {
                throw ValidationException::withMessages(['nom' => 'Un autre élève possède déjà cette identité ou ce numéro dans l’école.']);
            }
            $enrollment->eleve->update($data);

            return $enrollment->load('eleve');
        }, attempts: 3);
    }

    public function remove(Classe $class, Utilisateur $actor, int $id, string $reason): Inscription
    {
        return DB::transaction(function () use ($class, $actor, $id, $reason): Inscription {
            $class = $this->lockClass($class, $actor);

            return $this->exits->recordExit($this->enrollment($class, $id), $actor,
                new EnrollmentExitData('ABANDON', today(config('app.business_timezone'))->toDateString(), $reason));
        }, attempts: 3);
    }

    public function enrollment(Classe $class, int $id): Inscription
    {
        return Inscription::query()->where('tenant_id', $class->tenant_id)->where('classe_id', $class->id)
            ->whereHas('eleve', fn ($query) => $query->where('tenant_id', $class->tenant_id)->where('etablissement_id', $class->etablissement_id))
            ->where('annee_scolaire_id', $class->annee_scolaire_id)->with('eleve')->findOrFail($id);
    }

    private function lockClass(Classe $class, Utilisateur $actor): Classe
    {
        $class = Classe::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($actor->fresh())->authorize('manageOwnClassStudents', $class);
        $this->assertOpen($class);
        Etablissement::query()->whereKey($class->etablissement_id)->lockForUpdate()->firstOrFail();

        return $class;
    }
}
