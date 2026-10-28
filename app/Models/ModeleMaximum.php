<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModeleMaximum extends Model
{
    protected $table = 'modele_maxima';

    protected $fillable = [
        'tenant_id',
        'modele_bulletin_id',
        'node_id',
        'periode_id',
        'maxima',
        'coefficient',
    ];

    protected function casts(): array
    {
        return [
            'maxima' => 'decimal:2',
            'coefficient' => 'decimal:2',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(ModelePeriode::class);
    }

    public function modeleBulletin(): BelongsTo
    {
        return $this->belongsTo(ModeleBulletin::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(ModeleNode::class);
    }
}
