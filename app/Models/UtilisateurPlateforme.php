<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Identité d'administration plateforme.
 *
 * Cette identité est volontairement séparée de {@see Utilisateur} :
 * elle n'a pas de `tenant_id` ni `etablissement_id` et utilise un guard
 * distinct via le type de token Sanctum `context:platform`.
 */
class UtilisateurPlateforme extends Model implements Authenticatable, AuthorizableContract
{
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use \Illuminate\Auth\Authenticatable;

    protected $table = 'utilisateurs_plateforme';

    protected $fillable = [
        'public_id',
        'nom',
        'postnom',
        'prenom',
        'email',
        'mot_de_passe',
        'actif',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    /** @return BelongsToMany<RolePlateforme> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            RolePlateforme::class,
            'utilisateur_plateforme_roles',
            'utilisateur_plateforme_id',
            'role_code',
            'id',
            'code',
        );
    }

    public function isActive(): bool
    {
        return $this->actif === true;
    }
}
