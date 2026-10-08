<?php

namespace App\Models;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteHistorique extends Model
{
    protected $table = 'notes_historiques';

    protected $fillable = [
        'note_id',
        'ancien_statut_resultat',
        'nouveau_statut_resultat',
        'ancienne_valeur',
        'nouvelle_valeur',
        'modifie_par',
        'motif',
    ];

    protected $casts = [
        'ancien_statut_resultat' => NoteResultStatus::class,
        'nouveau_statut_resultat' => NoteResultStatus::class,
        'ancienne_valeur' => 'decimal:2',
        'nouvelle_valeur' => 'decimal:2',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function modifiePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'modifie_par');
    }
}
