<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefNiveau extends Model
{
    protected $table = 'ref_niveaux';

    protected $guarded = [];

    public function degre(): BelongsTo
    {
        return $this->belongsTo(RefDegre::class, 'degre_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RefCycle::class, 'cycle_id');
    }
}
