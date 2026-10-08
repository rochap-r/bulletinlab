<?php

namespace App\Domains\Teacher\Services;

use App\Domains\Teacher\DTOs\LockedCourseAssignmentNoteContext;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\SuiviEncodageNote;
use App\Models\Utilisateur;

final class UpdateCourseAssignmentNoteProgressService
{
    public function update(
        LockedCourseAssignmentNoteContext $context,
        Utilisateur $teacher,
    ): void {
        $schoolClass = $context->schoolClass;
        $totalStudents = Inscription::query()
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('classe_id', $schoolClass->getKey())
            ->where('annee_scolaire_id', $schoolClass->annee_scolaire_id)
            ->where('statut', 'ACTIF')
            ->count();
        $recordedNotes = Note::query()
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('node_id', $context->course->getKey())
            ->where('periode_id', $context->slot->periode_id)
            ->recordedResults()
            ->whereHas(
                'bulletin',
                fn ($reportCard) => $reportCard
                    ->where('tenant_id', $schoolClass->tenant_id)
                    ->where('classe_id', $schoolClass->getKey())
                    ->where(
                        'modele_bulletin_id',
                        $context->model->getKey(),
                    )
                    ->whereHas(
                        'inscription',
                        fn ($enrollment) => $enrollment
                            ->where(
                                'annee_scolaire_id',
                                $schoolClass->annee_scolaire_id,
                            )
                            ->where('statut', 'ACTIF'),
                    ),
            )
            ->distinct()
            ->count('bulletin_id');
        $percentage = $totalStudents > 0
            ? ($recordedNotes / $totalStudents) * 100
            : 0;
        $status = match (true) {
            $percentage >= 100 => 'TERMINE',
            $percentage > 0 => 'EN_COURS',
            default => 'NON_COMMENCE',
        };
        $progress = SuiviEncodageNote::query()
            ->where('tenant_id', $schoolClass->tenant_id)
            ->where('classe_id', $schoolClass->getKey())
            ->where('node_id', $context->course->getKey())
            ->where('periode_id', $context->slot->periode_id)
            ->lockForUpdate()
            ->first();
        $values = [
            'tenant_id' => $schoolClass->tenant_id,
            'classe_id' => $schoolClass->getKey(),
            'modele_bulletin_id' => $context->model->getKey(),
            'node_id' => $context->course->getKey(),
            'periode_id' => $context->slot->periode_id,
            'enseignant_id' => $teacher->getKey(),
            'nombre_eleves' => $totalStudents,
            'nombre_notes_saisies' => $recordedNotes,
            'pourcentage_avancement' => $percentage,
            'statut' => $status,
            'derniere_saisie_at' => now(),
        ];

        if ($progress instanceof SuiviEncodageNote) {
            $progress->update($values);

            return;
        }

        SuiviEncodageNote::query()->create($values);
    }
}
