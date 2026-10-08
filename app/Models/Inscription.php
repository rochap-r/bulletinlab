<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscription extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'inscriptions';

    protected $fillable = [
        'tenant_id',
        'eleve_id',
        'classe_id',
        'annee_scolaire_id',
        'numero_inscription',
        'statut',
        'date_inscription',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bulletin(): HasOne
    {
        return $this->hasOne(Bulletin::class);
    }
}
