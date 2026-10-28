<?php

namespace App\Models;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultatEvaluation extends Model
{
    use HasTenant;

    protected $table = 'resultats_evaluations';

    protected $attributes = [
        'statut_resultat' => NoteResultStatus::Saisie->value,
        'lock_version' => 1,
    ];

    protected $fillable = [
        'tenant_id',
        'evaluation_id',
        'inscription_id',
        'eleve_id',
        'statut_resultat',
        'valeur',
        'commentaire_parent',
        'commentaire_interne',
        'saisi_par',
        'date_saisie',
        'lock_version',
    ];

    protected $casts = [
        'statut_resultat' => NoteResultStatus::class,
        'valeur' => 'decimal:2',
        'date_saisie' => 'datetime',
        'lock_version' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'saisi_par');
    }

    public function isRecorded(): bool
    {
        return $this->statut_resultat === NoteResultStatus::Saisie
            && $this->valeur !== null;
    }
}
