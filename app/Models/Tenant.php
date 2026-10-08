<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = [
        'valide_at' => 'datetime',
    ];

    /**
     * @return HasMany<Etablissement, $this>
     */
    public function etablissements(): HasMany
    {
        return $this->hasMany(Etablissement::class);
    }

    /**
     * @return BelongsTo<UtilisateurPlateforme, $this>
     */
    public function validePar(): BelongsTo
    {
        return $this->belongsTo(UtilisateurPlateforme::class, 'valide_par_id');
    }

    public function isPendingValidation(): bool
    {
        return $this->statut === 'EN_ATTENTE';
    }
}
