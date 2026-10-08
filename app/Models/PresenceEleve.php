<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresenceEleve extends Model
{
    use HasTenant;

    protected $table = 'presences_eleves';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'annee_scolaire_id',
        'classe_id',
        'inscription_id',
        'periode_id',
        'date_presence',
        'moment',
        'statut',
        'observation',
        'saisi_par',
    ];

    protected $casts = [
        'date_presence' => 'date',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class, 'periode_id');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'saisi_par');
    }
}
