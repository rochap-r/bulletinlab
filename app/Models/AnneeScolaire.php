<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnneeScolaire extends Model
{
    use HasTenant;

    protected $table = 'annees_scolaires';
    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function calendrierScolaire(): BelongsTo
    {
        return $this->belongsTo(CalendrierScolaire::class, 'calendrier_scolaire_id');
    }
}
