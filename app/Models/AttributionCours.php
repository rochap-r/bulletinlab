<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributionCours extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'attributions_cours';

    protected $fillable = [
        'tenant_id',
        'classe_id',
        'modele_bulletin_id',
        'node_id',
        'enseignant_id',
        'date_debut',
        'date_fin',
        'actif',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'actif' => 'boolean',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function modeleBulletin(): BelongsTo
    {
        return $this->belongsTo(ModeleBulletin::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ModeleNode::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'enseignant_id');
    }

    /**
     * @param  Builder<AttributionCours>  $query
     * @return Builder<AttributionCours>
     */
    public function scopeEffectiveOn(
        Builder $query,
        CarbonInterface|string $date,
    ): Builder {
        $effectiveDate = CarbonImmutable::parse(
            $date instanceof CarbonInterface ? $date->toDateString() : $date,
        );
        $startDateBoundary = $effectiveDate->endOfDay()->toDateTimeString();
        $endDateBoundary = $effectiveDate->toDateString();
        $activeColumn = $query->qualifyColumn('actif');
        $startDateColumn = $query->qualifyColumn('date_debut');
        $endDateColumn = $query->qualifyColumn('date_fin');

        return $query
            ->where($activeColumn, true)
            ->where(function (Builder $query) use (
                $startDateBoundary,
                $startDateColumn,
            ): void {
                $query
                    ->whereNull($startDateColumn)
                    ->orWhere($startDateColumn, '<=', $startDateBoundary);
            })
            ->where(function (Builder $query) use (
                $endDateBoundary,
                $endDateColumn,
            ): void {
                $query
                    ->whereNull($endDateColumn)
                    ->orWhere($endDateColumn, '>=', $endDateBoundary);
            });
    }
}
