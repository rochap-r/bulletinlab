<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OuvertureAnnuelle extends Model
{
    use HasTenant;

    public const STATUT_OUVERTE = 'OUVERTE';

    public const STATUT_CLOTUREE = 'CLOTUREE';

    protected $table = 'ouvertures_annuelles';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'annee_scolaire_id',
        'statut',
        'activee_at',
        'activee_par',
        'cloturee_at',
        'cloturee_par',
    ];

    protected $casts = [
        'activee_at' => 'datetime',
        'cloturee_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    public function isOuverte(): bool
    {
        return $this->statut === self::STATUT_OUVERTE;
    }

    public function isCloturee(): bool
    {
        return $this->statut === self::STATUT_CLOTUREE;
    }
}
