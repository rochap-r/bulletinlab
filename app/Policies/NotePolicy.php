<?php

namespace App\Policies;

use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Models\AttributionCours;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\ModeleMaximum;
use App\Models\Note;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;

final class NotePolicy
{
    public function __construct(
        private EstablishmentContext $establishmentContext,
        private EtablissementPolicy $establishmentPolicy,
        private ClassePolicy $classPolicy,
        private AttributionCoursPolicy $assignmentPolicy,
    ) {}

    public function viewAny(Utilisateur $utilisateur): bool
    {
        return $this->establishmentContext->has()
            && $this->establishmentPolicy->manageAcademicStructure(
                $utilisateur,
                $this->establishmentContext->current(),
            );
    }

    public function viewClass(
        Utilisateur $utilisateur,
        Classe $classe,
    ): bool {
        return $this->establishmentContext->has()
            && $classe->etablissement_id
                === $this->establishmentContext->id()
            && $this->classPolicy->viewCourses($utilisateur, $classe);
    }

    public function view(Utilisateur $utilisateur, Note $note): bool
    {
        $bulletin = $this->resolveReportCard($note);
        $classe = $this->resolveClass($bulletin);

        if (! $bulletin
            || ! $classe
            || $note->tenant_id !== $utilisateur->tenant_id
            || $bulletin->tenant_id !== $note->tenant_id
            || $classe->tenant_id !== $note->tenant_id
            || $bulletin->classe_id !== $classe->getKey()) {
            return false;
        }

        if ($this->viewClass($utilisateur, $classe)) {
            return true;
        }

        return $this->teacherHasCurrentSlot(
            $utilisateur,
            $note,
            $bulletin,
            $classe,
        );
    }

    public function viewHistory(
        Utilisateur $utilisateur,
        Note $note,
    ): bool {
        return $this->view($utilisateur, $note);
    }

    private function teacherHasCurrentSlot(
        Utilisateur $utilisateur,
        Note $note,
        Bulletin $bulletin,
        Classe $classe,
    ): bool {
        $today = CarbonImmutable::today(
            (string) config('app.business_timezone'),
        );
        $assignments = AttributionCours::withoutGlobalScopes()
            ->where('tenant_id', $note->tenant_id)
            ->where('classe_id', $classe->getKey())
            ->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->where('node_id', $note->node_id)
            ->where('enseignant_id', $utilisateur->getKey())
            ->effectiveOn($today)
            ->limit(2)
            ->get();

        if ($assignments->count() !== 1) {
            return false;
        }

        $slots = ModeleMaximum::withoutGlobalScopes()
            ->where('tenant_id', $note->tenant_id)
            ->where('modele_bulletin_id', $bulletin->modele_bulletin_id)
            ->where('node_id', $note->node_id)
            ->where('periode_id', $note->periode_id)
            ->where('maxima', '>', 0)
            ->limit(2)
            ->get();

        $assignment = $assignments->first();
        $slot = $slots->first();

        return $slots->count() === 1
            && $assignment instanceof AttributionCours
            && $slot instanceof ModeleMaximum
            && $this->assignmentPolicy->viewGradeSheet(
                $utilisateur,
                $assignment,
                $slot,
            );
    }

    private function resolveReportCard(Note $note): ?Bulletin
    {
        if ($note->relationLoaded('bulletin')) {
            $bulletin = $note->getRelation('bulletin');

            return $bulletin instanceof Bulletin ? $bulletin : null;
        }

        return Bulletin::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'classe_id',
                'modele_bulletin_id',
            ])
            ->find($note->bulletin_id);
    }

    private function resolveClass(?Bulletin $bulletin): ?Classe
    {
        if (! $bulletin) {
            return null;
        }

        if ($bulletin->relationLoaded('classe')) {
            $classe = $bulletin->getRelation('classe');

            return $classe instanceof Classe ? $classe : null;
        }

        return Classe::withoutGlobalScopes()
            ->select([
                'id',
                'tenant_id',
                'etablissement_id',
                'titulaire_id',
                'active',
            ])
            ->find($bulletin->classe_id);
    }
}
