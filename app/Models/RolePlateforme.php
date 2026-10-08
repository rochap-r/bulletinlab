<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rôle d'administration plateforme.
 *
 * Le code (clé primaire) est un code stable et lisible, par exemple
 * `ADMIN_PLATEFORME` ou `SUPPORT_PLATEFORME`.
 */
class RolePlateforme extends Model
{
    protected $table = 'roles_plateforme';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'code',
        'nom',
    ];
}
