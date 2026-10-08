<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResultatBulletin extends Model
{
    use HasTenant;

    protected $table = 'resultats_bulletins';

    protected $fillable = [
        'tenant_id',
        'bulletin_id',
        'periode_id',
        'modele_bulletin_id',
        'version',
        'engine_version',
        'configuration_hash',
        'input_hash',
        'result_hash',
        'total_obtenu',
        'total_maxima',
        'pourcentage',
        'mention',
        'decision',
        'rang',
        'nombre_eleves_classe',
        'payload',
    ];

    protected $casts = [
        'total_obtenu' => 'decimal:2',
        'total_maxima' => 'decimal:2',
        'pourcentage' => 'decimal:2',
        'version' => 'integer',
        'rang' => 'integer',
        'nombre_eleves_classe' => 'integer',
        'payload' => 'array',
    ];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class);
    }

    public function publication(): HasOne
    {
        return $this->hasOne(Publication::class, 'resultat_bulletin_id');
    }
}
