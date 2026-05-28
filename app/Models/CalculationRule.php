<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalculationRule extends Model
{
    protected $table = 'calculation_rules';
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];
}
