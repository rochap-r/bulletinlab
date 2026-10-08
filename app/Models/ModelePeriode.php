<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelePeriode extends Model
{
    protected $table = 'modele_periodes';

    protected $fillable = [
        'tenant_id',
        'modele_bulletin_id',
        'system_periode_id',
        'parent_id',
        'code',
        'nom',
        'type_periode',
        'ordre',
        'saisissable',
        'calculable',
        'actif',
    ];

    protected $casts = [
        'ordre' => 'integer',
        'saisissable' => 'boolean',
        'calculable' => 'boolean',
        'actif' => 'boolean',
    ];

    public function modeleBulletin(): BelongsTo
    {
        return $this->belongsTo(ModeleBulletin::class);
    }
}
