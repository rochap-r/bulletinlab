<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $table = 'paiements';

    protected $fillable = [
        'tenant_id',
        'abonnement_id',
        'montant',
        'devise',
        'methode',
        'reference_externe',
        'statut',
        'paid_at',
    ];
}
