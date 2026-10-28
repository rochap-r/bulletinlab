<?php

namespace App\Models;

use App\Enums\StatutLienResponsable;
use App\Enums\TypeLienResponsable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Database\Factories\LienResponsableEleveFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LienResponsableEleve extends Model
{
    /** @use HasFactory<LienResponsableEleveFactory> */
    use HasFactory;

    use HasPublicId;
    use HasTenant;

    protected $table = 'lien_responsable_eleves';

    protected $fillable = [
        'tenant_id',
        'responsable_id',
        'eleve_id',
        'type_lien',
        'statut',
        'principal',
        'peut_consulter_notes',
        'peut_consulter_presences',
        'recevoir_notifications',
        'declare_par',
        'verifie_par',
        'verifie_at',
        'methode_verification',
        'revoque_par',
        'revoque_at',
        'motif_revocation',
    ];

    protected $attributes = [
        'type_lien' => 'TUTEUR',
        'statut' => 'EN_ATTENTE',
        'principal' => false,
        'peut_consulter_notes' => true,
        'peut_consulter_presences' => true,
        'recevoir_notifications' => true,
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class);
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function declarePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'declare_par');
    }

    public function verifiePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'verifie_par');
    }

    public function revoquePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'revoque_par');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query
            ->where('statut', StatutLienResponsable::Verifie)
            ->whereNotNull('verifie_at')
            ->whereNull('revoque_at');
    }

    public function isVerified(): bool
    {
        return $this->statut === StatutLienResponsable::Verifie
            && $this->verifie_at !== null
            && $this->revoque_at === null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type_lien' => TypeLienResponsable::class,
            'statut' => StatutLienResponsable::class,
            'principal' => 'boolean',
            'peut_consulter_notes' => 'boolean',
            'peut_consulter_presences' => 'boolean',
            'recevoir_notifications' => 'boolean',
            'verifie_at' => 'datetime',
            'revoque_at' => 'datetime',
        ];
    }
}
