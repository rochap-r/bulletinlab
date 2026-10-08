<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Model implements Authenticatable, AuthorizableContract
{
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasPublicId;
    use \Illuminate\Auth\Authenticatable;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'nom',
        'postnom',
        'prenom',
        'email',
        'telephone',
        'mot_de_passe',
        'photo_path',
        'actif',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'utilisateur_roles', 'utilisateur_id', 'role_id');
    }

    public function attributionsCours(): HasMany
    {
        return $this->hasMany(AttributionCours::class, 'enseignant_id');
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(
            fn (): string => collect([$this->nom, $this->postnom, $this->prenom])
                ->filter()
                ->implode(' '),
        );
    }
}
