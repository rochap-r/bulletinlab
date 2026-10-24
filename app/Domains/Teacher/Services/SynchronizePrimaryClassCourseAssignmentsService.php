<?php

namespace App\Domains\Teacher\Services;

use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\ModeleNode;
use App\Models\Utilisateur;
use Illuminate\Database\Eloquent\Builder;

final class SynchronizePrimaryClassCourseAssignmentsService
{
    public function synchronize(
        Classe $class,
        Utilisateur $titular,
        string $startDate,
        ?string $endDate,
    ): void {
        if (! $class->isPrimarySchool()) {
            return;
        }

        $courses = ModeleNode::query()
            ->where('tenant_id', $class->tenant_id)
            ->where('type_node', 'MATIERE')
            ->whereHas('modeleBulletin', function (Builder $query) use ($class): void {
                $query
                    ->where('tenant_id', $class->tenant_id)
                    ->where(function ($q) use ($class): void {
                        // Modèle lié à la classe, ou modèle système global (sans classe_id).
                        $q->where('classe_id', $class->getKey())
                            ->orWhereNull('classe_id');
                    })
                    ->where(function ($q) use ($class): void {
                        // Année scolaire de la classe, ou modèle système sans année.
                        $q->where('annee_scolaire_id', $class->annee_scolaire_id)
                            ->orWhereNull('annee_scolaire_id');
                    })
                    ->where('actif', true);
            })
            ->whereHas('maxima', fn (Builder $query): Builder => $query->where('maxima', '>', 0))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($courses as $course) {
            $assignments = AttributionCours::query()
                ->where('tenant_id', $class->tenant_id)
                ->where('classe_id', $class->getKey())
                ->where('modele_bulletin_id', $course->modele_bulletin_id)
                ->where('node_id', $course->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $targetAssignment = $assignments->firstWhere(
                'enseignant_id',
                $titular->getKey(),
            );

            foreach ($assignments->where('actif', true) as $activeAssignment) {
                if ($targetAssignment?->is($activeAssignment)) {
                    continue;
                }

                $previousStartDate = $activeAssignment->date_debut?->toDateString();
                $closingDate = $previousStartDate !== null
                    && $previousStartDate > $startDate
                    ? $previousStartDate
                    : $startDate;

                $activeAssignment->update([
                    'date_fin' => $closingDate,
                    'actif' => false,
                ]);
            }

            if ($targetAssignment) {
                $targetAssignment->update([
                    'date_debut' => $startDate,
                    'date_fin' => $endDate,
                    'actif' => true,
                ]);

                continue;
            }

            AttributionCours::query()->create([
                'tenant_id' => $class->tenant_id,
                'classe_id' => $class->getKey(),
                'modele_bulletin_id' => $course->modele_bulletin_id,
                'node_id' => $course->getKey(),
                'enseignant_id' => $titular->getKey(),
                'date_debut' => $startDate,
                'date_fin' => $endDate,
                'actif' => true,
            ]);
        }
    }
}
