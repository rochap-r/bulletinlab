<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\Exceptions\ClassCourseListingConflictException;
use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\ModeleBulletin;
use App\Models\ModeleNode;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ListClassCoursesService
{
    public function paginate(Classe $class, int $perPage): LengthAwarePaginator
    {
        $reportCardModel = ModeleBulletin::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('classe_id', $class->getKey())
            ->where('annee_scolaire_id', $class->annee_scolaire_id)
            ->first();

        if (! $reportCardModel
            || ($class->active && ! $reportCardModel->actif)) {
            throw new ClassCourseListingConflictException(
                'The class has no available course configuration.',
            );
        }

        $hasMultipleActiveAssignments = AttributionCours::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('classe_id', $class->getKey())
            ->where('modele_bulletin_id', $reportCardModel->getKey())
            ->where('actif', true)
            ->select('node_id')
            ->groupBy('node_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasMultipleActiveAssignments) {
            throw new ClassCourseListingConflictException(
                'The class contains conflicting active course assignments.',
            );
        }

        return ModeleNode::query()
            ->select([
                'id',
                'tenant_id',
                'modele_bulletin_id',
                'matiere_id',
                'libelle',
                'ordre',
                'obligatoire',
            ])
            ->where('tenant_id', $class->tenant_id)
            ->where('modele_bulletin_id', $reportCardModel->getKey())
            ->where('type_node', 'MATIERE')
            ->withExists([
                'maxima as attribuable' => fn (Builder $query): Builder => $query
                    ->where('tenant_id', $class->tenant_id)
                    ->where(
                        'modele_bulletin_id',
                        $reportCardModel->getKey(),
                    )
                    ->where('maxima', '>', 0),
            ])
            ->with([
                'attributionsCours' => fn (HasMany $query): HasMany => $query
                    ->select([
                        'id',
                        'tenant_id',
                        'classe_id',
                        'modele_bulletin_id',
                        'node_id',
                        'enseignant_id',
                        'date_debut',
                        'date_fin',
                        'actif',
                    ])
                    ->where('tenant_id', $class->tenant_id)
                    ->where('classe_id', $class->getKey())
                    ->where(
                        'modele_bulletin_id',
                        $reportCardModel->getKey(),
                    )
                    ->where('actif', true)
                    ->with('enseignant:id,nom,postnom,prenom')
                    ->orderBy('id'),
            ])
            ->orderBy('ordre')
            ->orderBy('id')
            ->paginate($perPage);
    }
}
