<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class VerrouillagePeriode extends Model
{
    use HasTenant;

    protected $table = 'verrouillages_periodes';

    protected $fillable = [
        'tenant_id',
        'classe_id',
        'periode_id',
        'verrouille',
        'verrouille_par',
        'date_verrouillage',
    ];

    protected $casts = [
        'verrouille' => 'boolean',
        'date_verrouillage' => 'datetime',
    ];
}
