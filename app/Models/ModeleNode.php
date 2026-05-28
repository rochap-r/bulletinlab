<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeleNode extends Model
{
    protected $table = 'modele_nodes';
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];
}
