<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Model implements Authenticatable
{
    use HasFactory;
    use HasApiTokens;
    use \Illuminate\Auth\Authenticatable;

    protected $table = 'utilisateurs';
    protected $guarded = [];

    protected $hidden = [
        'mot_de_passe',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'utilisateur_roles', 'utilisateur_id', 'role_id');
    }

    public function hasRole(string $code): bool
    {
        return $this->roles()->where('code', $code)->exists();
    }
}
