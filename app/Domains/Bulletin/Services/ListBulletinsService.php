<?php

namespace App\Domains\Bulletin\Services;

use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\ModeleBulletin;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ListBulletinsService
{
    public function paginateForClass(
        Etablissement $establishment,
        Classe $classe,
        int $perPage,
        ?string $decisionStatus,
    ): LengthAwarePaginator {
        $query = $this->bulletinsForEstablishment($establishment)
            ->where('bulletins.classe_id', $classe->getKey())
            ->with($this->resourceRelations())
            ->orderByDesc('bulletins.created_at')
            ->orderByDesc('bulletins.id');

        if ($decisionStatus !== null) {
            $query->where('bulletins.decision_statut', $decisionStatus);
        }

        $bulletins = $query
            ->paginate($perPage)
            ->withQueryString();

        $bulletins->getCollection()->each(
            fn (Bulletin $bulletin) => $this->assertConsistent(
                $bulletin,
                $establishment,
            ),
        );

        return $bulletins;
    }

    public function findForEstablishment(
        Etablissement $establishment,
        int $bulletinId,
    ): Bulletin {
        $bulletin = $this->bulletinsForEstablishment($establishment)
            ->with($this->resourceRelations())
            ->findOrFail($bulletinId);

        $this->assertConsistent($bulletin, $establishment);

        return $bulletin;
    }

    public function findClassForEstablishment(
        Etablissement $establishment,
        int $classId,
    ): Classe {
        return Classe::withoutGlobalScopes()
            ->where('tenant_id', $establishment->tenant_id)
            ->where('etablissement_id', $establishment->getKey())
            ->findOrFail($classId);
    }

    /**
     * @return Builder<Bulletin>
     */
    private function bulletinsForEstablishment(
        Etablissement $establishment,
    ): Builder {
        return Bulletin::withoutGlobalScopes()
            ->where('bulletins.tenant_id', $establishment->tenant_id)
            ->whereHas(
                'classe',
                function (Builder $classes) use ($establishment): void {
                    $classes
                        ->withoutGlobalScopes()
                        ->where(
                            'classes.tenant_id',
                            $establishment->tenant_id,
                        )
                        ->where(
                            'classes.etablissement_id',
                            $establishment->getKey(),
                        );
                },
            );
    }

    /**
     * @return array<string, callable(BelongsTo): BelongsTo>
     */
    private function resourceRelations(): array
    {
        return [
            'classe' => fn (BelongsTo $classes): BelongsTo => $classes
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'etablissement_id',
                    'annee_scolaire_id',
                    'titulaire_id',
                    'active',
                ]),
            'inscription' => fn (BelongsTo $enrollments): BelongsTo => $enrollments
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'eleve_id',
                    'classe_id',
                    'annee_scolaire_id',
                ]),
            'inscription.eleve' => fn (BelongsTo $students): BelongsTo => $students
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'etablissement_id',
                    'num_permanent',
                    'nom',
                    'postnom',
                    'prenom',
                ]),
            'modeleBulletin' => fn (BelongsTo $models): BelongsTo => $models
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'classe_id',
                    'annee_scolaire_id',
                ]),
        ];
    }

    private function assertConsistent(
        Bulletin $bulletin,
        Etablissement $establishment,
    ): void {
        $classe = $bulletin->getRelation('classe');
        $enrollment = $bulletin->getRelation('inscription');
        $model = $bulletin->getRelation('modeleBulletin');
        $student = $enrollment instanceof Inscription
            ? $enrollment->getRelation('eleve')
            : null;

        if (! $classe instanceof Classe
            || ! $enrollment instanceof Inscription
            || ! $student instanceof Eleve
            || ! $model instanceof ModeleBulletin
            || $bulletin->tenant_id !== $establishment->tenant_id
            || $classe->tenant_id !== $bulletin->tenant_id
            || $classe->etablissement_id !== $establishment->getKey()
            || $bulletin->classe_id !== $classe->getKey()
            || $enrollment->tenant_id !== $bulletin->tenant_id
            || $enrollment->classe_id !== $classe->getKey()
            || $enrollment->annee_scolaire_id
                !== $classe->annee_scolaire_id
            || $student->tenant_id !== $bulletin->tenant_id
            || $student->etablissement_id !== $establishment->getKey()
            || $enrollment->eleve_id !== $student->getKey()
            || $model->tenant_id !== $bulletin->tenant_id
            || $model->classe_id !== $classe->getKey()
            || $model->annee_scolaire_id !== $classe->annee_scolaire_id) {
            throw new BulletinException(
                'The report card is inconsistent.',
            );
        }
    }
}
