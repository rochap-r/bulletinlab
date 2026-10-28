<?php

namespace App\Domains\Evaluation\Services;

use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Note;
use App\Models\NoteHistorique;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ListNotesService
{
    public function paginateForEstablishment(
        Etablissement $establishment,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->notesForEstablishment($establishment)
            ->with($this->resourceRelations())
            ->latest('notes.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function paginateForClass(
        Etablissement $establishment,
        Classe $classe,
        int $perPage,
    ): LengthAwarePaginator {
        return $this->notesForEstablishment($establishment)
            ->whereHas(
                'bulletin',
                fn (Builder $bulletins): Builder => $bulletins
                    ->withoutGlobalScopes()
                    ->where('classe_id', $classe->getKey()),
            )
            ->with($this->resourceRelations())
            ->latest('notes.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findForEstablishment(
        Etablissement $establishment,
        int $noteId,
    ): Note {
        return $this->notesForEstablishment($establishment)
            ->with($this->policyRelations())
            ->findOrFail($noteId);
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

    public function findResourceForEstablishment(
        Etablissement $establishment,
        int $noteId,
    ): Note {
        return $this->notesForEstablishment($establishment)
            ->with($this->resourceRelations())
            ->findOrFail($noteId);
    }

    public function paginateHistory(
        Note $note,
        int $perPage,
    ): LengthAwarePaginator {
        return NoteHistorique::query()
            ->select([
                'id',
                'note_id',
                'ancien_statut_resultat',
                'nouveau_statut_resultat',
                'ancienne_valeur',
                'nouvelle_valeur',
                'modifie_par',
                'motif',
                'created_at',
            ])
            ->where('note_id', $note->getKey())
            ->with([
                'modifiePar' => fn (BelongsTo $actors): BelongsTo => $actors
                    ->select([
                        'id',
                        'nom',
                        'postnom',
                        'prenom',
                    ]),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Builder<Note>
     */
    private function notesForEstablishment(
        Etablissement $establishment,
    ): Builder {
        return Note::withoutGlobalScopes()
            ->select([
                'notes.id',
                'notes.tenant_id',
                'notes.bulletin_id',
                'notes.node_id',
                'notes.periode_id',
                'notes.lock_version',
                'notes.statut_resultat',
                'notes.valeur',
                'notes.origine',
                'notes.calcul_version',
                'notes.calculee_at',
                'notes.saisie_par',
                'notes.date_saisie',
                'notes.commentaire',
                'notes.created_at',
                'notes.updated_at',
            ])
            ->where('notes.tenant_id', $establishment->tenant_id)
            ->whereHas(
                'bulletin',
                function (Builder $bulletins) use ($establishment): void {
                    $bulletins
                        ->withoutGlobalScopes()
                        ->where(
                            'bulletins.tenant_id',
                            $establishment->tenant_id,
                        )
                        ->whereHas(
                            'classe',
                            function (Builder $classes) use (
                                $establishment,
                            ): void {
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
                },
            );
    }

    /**
     * @return list<string>
     */
    private function resourceRelations(): array
    {
        return [
            'bulletin.inscription.eleve',
            'node',
            'periode',
            'saisiPar',
        ];
    }

    /**
     * @return array<string, callable(BelongsTo): BelongsTo>
     */
    private function policyRelations(): array
    {
        return [
            'bulletin' => fn (BelongsTo $bulletins): BelongsTo => $bulletins
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'classe_id',
                    'modele_bulletin_id',
                ]),
            'bulletin.classe' => fn (BelongsTo $classes): BelongsTo => $classes
                ->withoutGlobalScopes()
                ->select([
                    'id',
                    'tenant_id',
                    'etablissement_id',
                    'titulaire_id',
                    'active',
                ]),
        ];
    }
}
