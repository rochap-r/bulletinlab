<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $table = 'tenants';

    protected $fillable = [
        'uuid',
        'nom_espace',
        'slug',
        'type_tenant',
        'statut',
        'valide_at',
        'valide_par_id',
        'motif_refus',
    ];

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

    /**
     * @return HasMany<Utilisateur, $this>
     */
    public function utilisateurs(): HasMany
    {
        return $this->hasMany(Utilisateur::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isPendingValidation(): bool
    {
        return $this->statut === 'EN_ATTENTE';
    }
}
