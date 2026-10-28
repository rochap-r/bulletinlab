<?php

namespace App\Models;

use App\Enums\StatutEvaluation;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'evaluations';

    protected $attributes = [
        'statut' => StatutEvaluation::Brouillon->value,
        'coefficient' => 1,
        'lock_version' => 1,
    ];

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'annee_scolaire_id',
        'classe_id',
        'attribution_cours_id',
        'node_id',
        'periode_id',
        'type_evaluation_id',
        'libelle',
        'maxima',
        'coefficient',
        'date_evaluation',
        'statut',
        'visible_parents_at',
        'cree_par',
        'lock_version',
    ];

    protected $casts = [
        'maxima' => 'decimal:2',
        'coefficient' => 'decimal:2',
        'date_evaluation' => 'date',
        'statut' => StatutEvaluation::class,
        'visible_parents_at' => 'datetime',
        'lock_version' => 'integer',
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
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function attributionCours(): BelongsTo
    {
        return $this->belongsTo(AttributionCours::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ModeleNode::class, 'node_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class, 'periode_id');
    }

    public function typeEvaluation(): BelongsTo
    {
        return $this->belongsTo(TypeEvaluation::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'cree_par');
    }

    public function resultats(): HasMany
    {
        return $this->hasMany(ResultatEvaluation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function calculationSummary(): array
    {
        $maxima = (float) $this->maxima;
        $coefficient = (float) $this->coefficient;

        return [
            'maxima' => $maxima,
            'coefficient' => $coefficient,
            'pondered_maxima' => round($maxima * $coefficient, 2),
        ];
    }
}
