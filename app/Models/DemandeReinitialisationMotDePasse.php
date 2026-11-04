<?php

namespace App\Models;

use Database\Factories\DemandeReinitialisationMotDePasseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class DemandeReinitialisationMotDePasse extends Model
{
    /** @use HasFactory<DemandeReinitialisationMotDePasseFactory> */
    use HasFactory;

    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';

    public const STATUT_TRAITEE = 'TRAITEE';

    public const DESTINATAIRE_PLATEFORME = 'PLATEFORME';

    public const DESTINATAIRE_ECOLE = 'ECOLE';

    protected $table = 'demandes_reinitialisation_mot_de_passe';

    protected $fillable = [
        'utilisateur_id',
        'tenant_id',
        'traitee_par_id',
        'traitee_par_utilisateur_id',
        'destinataire',
        'statut',
        'ip_address',
        'user_agent',
        'traitee_at',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function traiteePar(): BelongsTo
    {
        return $this->belongsTo(UtilisateurPlateforme::class, 'traitee_par_id');
    }

    public function traiteeParUtilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'traitee_par_utilisateur_id');
    }

    /** @param Builder<self> $query */
    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'traitee_at' => 'datetime',
        ];
    }
}
