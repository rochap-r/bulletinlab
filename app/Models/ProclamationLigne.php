<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProclamationLigne extends Model
{
    protected $table = 'proclamation_lignes';
    protected $guarded = [];

    protected $casts = [
        'details' => 'array',
    ];
}
