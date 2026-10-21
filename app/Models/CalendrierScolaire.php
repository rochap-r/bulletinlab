<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CalendrierScolaire extends Model
{
    protected $table = 'calendriers_scolaires';

    protected $fillable = [
        'code',
        'libelle',
        'pays',
        'date_debut',
        'date_fin',
        'officiel',
        'actif',
    ];

    /** @return HasMany<CalendrierPeriode, $this> */
    public function periodes(): HasMany
    {
        return $this->hasMany(CalendrierPeriode::class, 'calendrier_scolaire_id')->orderBy('ordre');
    }

    /** @return HasMany<CalendrierCycleParametre, $this> */
    public function profilsCycles(): HasMany
    {
        return $this->hasMany(CalendrierCycleParametre::class, 'calendrier_scolaire_id')->orderBy('id');
    }

    /** @return HasMany<CalendrierEvenement, $this> */
    public function evenements(): HasMany
    {
        return $this->hasMany(CalendrierEvenement::class, 'calendrier_scolaire_id')
            ->orderBy('date_debut')
            ->orderBy('id');
    }

    /** @return HasMany<AnneeScolaire, $this> */
    public function annees(): HasMany
    {
        return $this->hasMany(AnneeScolaire::class, 'calendrier_scolaire_id')
            ->withoutGlobalScopes()
            ->orderByDesc('date_debut');
    }

    /** @return HasManyThrough<AnneePeriodeMapping, CalendrierPeriode, $this> */
    public function mappings(): HasManyThrough
    {
        return $this->hasManyThrough(AnneePeriodeMapping::class, CalendrierPeriode::class, 'calendrier_scolaire_id', 'calendrier_periode_id');
    }

    public function isUsed(): bool
    {
        return $this->annees()->exists() || $this->mappings()->exists();
    }
}
