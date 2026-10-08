<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTemplateBloc extends Model
{
    protected $table = 'system_template_blocs';

    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];

    public function bloc(): BelongsTo
    {
        return $this->belongsTo(SystemBlocSpecial::class, 'bloc_id');
    }
}
