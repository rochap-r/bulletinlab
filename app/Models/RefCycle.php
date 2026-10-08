<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefCycle extends Model
{
    protected $table = 'ref_cycles';

    protected $guarded = [];

    /** @return HasMany<RefDegre, $this> */
    public function degres(): HasMany
    {
        return $this->hasMany(RefDegre::class, 'cycle_id')->orderBy('ordre');
    }

    /** @return HasMany<RefNiveau, $this> */
    public function niveaux(): HasMany
    {
        return $this->hasMany(RefNiveau::class, 'cycle_id')->orderBy('ordre');
    }

    /** @return HasMany<RefOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(RefOption::class, 'cycle_id')->orderBy('id');
    }
}
