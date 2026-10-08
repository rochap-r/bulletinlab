<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publication extends Model
{
    use HasTenant;

    protected $table = 'publications';

    protected $fillable = [
        'tenant_id',
        'bulletin_id',
        'periode_id',
        'resultat_bulletin_id',
        'checksum',
        'engine_version',
        'statut',
        'publiee_par',
        'publiee_at',
    ];

    protected $casts = [
        'publiee_at' => 'datetime',
    ];

    public function bulletin(): BelongsTo
    {
        return $this->belongsTo(Bulletin::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class);
    }

    public function resultatBulletin(): BelongsTo
    {
        return $this->belongsTo(ResultatBulletin::class, 'resultat_bulletin_id');
    }

    public function rectifications(): HasMany
    {
        return $this->hasMany(Rectification::class, 'publication_id');
    }
}
