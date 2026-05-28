<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportFichier extends Model
{
    protected $table = 'imports_fichiers';
    protected $guarded = [];

    protected $casts = [
        'rapport' => 'array',
    ];
}
