<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTemplateNode extends Model
{
    protected $table = 'system_template_nodes';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
