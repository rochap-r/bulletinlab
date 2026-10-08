<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionEleve extends Model
{
    protected $table = 'promotions_eleves';

    protected $guarded = [];

    /** @return BelongsTo<PromotionAnnuelle, $this> */
    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PromotionAnnuelle::class, 'promotion_id');
    }

    /** @return BelongsTo<Inscription, $this> */
    public function inscriptionSource(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'inscription_source_id');
    }

    /** @return BelongsTo<Classe, $this> */
    public function classeCible(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_cible_id');
    }

    /** @return BelongsTo<Inscription, $this> */
    public function inscriptionCible(): BelongsTo
    {
        return $this->belongsTo(Inscription::class, 'inscription_cible_id');
    }
}
