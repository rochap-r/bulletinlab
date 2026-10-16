<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Abonnement extends Model
{
    protected $table = 'abonnements';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'statut',
        'date_debut',
        'date_fin',
        'trial_ends_at',
    ];
}
