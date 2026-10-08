<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionAnnuelle extends Model
{
    protected $table = 'promotions_annuelles';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];

    /** @return HasMany<PromotionEleve, $this> */
    public function eleves(): HasMany
    {
        return $this->hasMany(PromotionEleve::class, 'promotion_id');
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeSource(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_source_id');
    }

    /** @return BelongsTo<AnneeScolaire, $this> */
    public function anneeCible(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_cible_id');
    }

    /** @return BelongsTo<Publication, $this> */
    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }
}
