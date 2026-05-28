<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionAnnuelle extends Model
{
    protected $table = 'promotions_annuelles';
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];
}
