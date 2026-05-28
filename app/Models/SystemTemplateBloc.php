<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTemplateBloc extends Model
{
    protected $table = 'system_template_blocs';
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];
}
