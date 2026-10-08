<?php

namespace App\Models;

use App\Domains\Evaluation\Enums\NoteResultStatus;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Note extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'notes';

    protected $attributes = [
        'statut_resultat' => 'SAISIE',
        'lock_version' => 1,
    ];

    protected $fillable = [
        'tenant_id',
        'bulletin_id',
        'node_id',
        'periode_id',
        'statut_resultat',
        'valeur',
        'saisie_par',
        'date_saisie',
        'commentaire',
    ];

    protected $casts = [
        'statut_resultat' => NoteResultStatus::class,
        'lock_version' => 'integer',
        'valeur' => 'decimal:2',
        'date_saisie' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ModeleNode::class, 'node_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class, 'periode_id');
    }

    public function saisiPar(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'saisie_par');
    }

    public function historique(): HasMany
    {
        return $this->hasMany(NoteHistorique::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRecordedResults(Builder $query): Builder
    {
        return $query->where(function (Builder $result): void {
            $result
                ->where(function (Builder $recorded): void {
                    $recorded
                        ->where(
                            'statut_resultat',
                            NoteResultStatus::Saisie->value,
                        )
                        ->whereNotNull('valeur');
                })
                ->orWhereIn('statut_resultat', [
                    NoteResultStatus::Absente->value,
                    NoteResultStatus::Dispensee->value,
                ]);
        });
    }
}
