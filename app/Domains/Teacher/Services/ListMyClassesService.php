<?php

namespace App\Domains\Teacher\Services;

use App\Models\AttributionCours;
use App\Models\Classe;
use App\Models\Utilisateur;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final class ListMyClassesService
{
    /** @return Collection<int, Classe> */
    public function list(Utilisateur $teacher, int $establishmentId): Collection
    {
        $today = CarbonImmutable::today((string) config('app.business_timezone'));

        return Classe::query()
            ->select(['id', 'tenant_id', 'etablissement_id', 'annee_scolaire_id', 'nom', 'code', 'titulaire_id', 'active'])
            ->where('tenant_id', $teacher->tenant_id)
            ->where('etablissement_id', $establishmentId)
            ->where('active', true)
            ->whereHas('anneeScolaire', function (Builder $query) use ($today): void {
                $query->where('active', true)
                    ->where('cloturee', false)
                    ->whereDate('date_debut', '<=', $today)
                    ->whereDate('date_fin', '>=', $today);
            })
            ->where(function (Builder $query) use ($teacher, $today): void {
                $query->where('titulaire_id', $teacher->getKey())
                    ->orWhereHas('attributionsCours', function (Builder $query) use ($teacher, $today): void {
                        $this->scopeTeacherAssignments($query, $teacher, $today);
                    });
            })
            ->with([
                'anneeScolaire' => fn (BelongsTo $query): BelongsTo => $query->select(['id', 'libelle']),
                'attributionsCours' => function (HasMany $query) use ($teacher, $today): void {
                    $this->scopeTeacherAssignments($query, $teacher, $today);
                    $query
                        ->select(['id', 'tenant_id', 'classe_id', 'modele_bulletin_id', 'node_id', 'enseignant_id', 'date_debut', 'date_fin', 'actif'])
                        ->with(['node' => fn (BelongsTo $query): BelongsTo => $query->select(['id', 'matiere_id', 'libelle', 'ordre'])])
                        ->orderBy('id');
                },
            ])
            ->orderBy('nom')
            ->get();
    }

    /** @param Builder<AttributionCours>|HasMany<AttributionCours, Classe> $query */
    private function scopeTeacherAssignments(Builder|HasMany $query, Utilisateur $teacher, CarbonImmutable $today): void
    {
        $query
            ->where('enseignant_id', $teacher->getKey())
            ->effectiveOn($today)
            ->whereHas('modeleBulletin', fn (Builder $query): Builder => $query->where('actif', true))
            ->whereHas('node', fn (Builder $query): Builder => $query->where('type_node', 'MATIERE'));
    }
}
