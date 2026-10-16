<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';

    protected $fillable = [
        'code',
        'nom',
        'prix_mensuel',
        'prix_annuel',
        'limite_eleves',
        'limite_utilisateurs',
        'limite_etablissements',
        'actif',
    ];
}
