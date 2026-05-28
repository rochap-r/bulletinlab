<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeleBloc extends Model
{
    protected $table = 'modele_blocs';
    protected $guarded = [];

    protected $casts = [
        'configuration' => 'array',
    ];
}
