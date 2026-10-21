<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModeleBloc extends Model
{
    protected $table = 'modele_blocs';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
        'actif' => 'boolean',
    ];

    public function bloc(): BelongsTo
    {
        return $this->belongsTo(SystemBlocSpecial::class, 'bloc_id');
    }
}
