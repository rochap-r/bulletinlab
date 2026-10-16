<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Etablissement extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'etablissements';

    protected $fillable = [
        'tenant_id',
        'nom',
        'type_etablissement',
        'code_ministere',
        'code_interne',
        'province_educationnelle',
        'province',
        'ville',
        'commune_territoire',
        'adresse',
        'telephone',
        'email',
        'logo_path',
        'sceau_path',
        'signature_directeur_path',
        'actif',
    ];

    protected $attributes = [
        'type_etablissement' => 'MIXTE',
        'actif' => true,
    ];
}
