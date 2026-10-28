<?php

namespace App\Models;

use App\Enums\StatutLienResponsable;
use App\Models\Concerns\HasPublicId;
use App\Support\PhoneNumberNormalizer;
use Database\Factories\ResponsableFactory;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;

class Responsable extends Model implements Authenticatable, AuthorizableContract
{
    use Authorizable;
    use HasApiTokens;

    /** @use HasFactory<ResponsableFactory> */
    use HasFactory;

    use HasPublicId;
    use \Illuminate\Auth\Authenticatable;

    protected $fillable = [
        'nom',
        'postnom',
        'prenom',
        'telephone',
        'email',
        'mot_de_passe',
        'doit_changer_mot_de_passe',
        'telephone_verifie_at',
        'derniere_connexion_at',
        'actif',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected $attributes = [
        'actif' => true,
        'doit_changer_mot_de_passe' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (Responsable $responsable): void {
            if ($responsable->isDirty('telephone') || blank($responsable->telephone_normalise)) {
                $responsable->telephone_normalise = PhoneNumberNormalizer::normalize($responsable->telephone);
            }
        });
    }

    public function liensEleves(): HasMany
    {
        return $this->hasMany(LienResponsableEleve::class);
    }

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(
            Eleve::class,
            'lien_responsable_eleves',
        )->withPivot([
            'public_id',
            'tenant_id',
            'type_lien',
            'statut',
            'principal',
            'peut_consulter_notes',
            'peut_consulter_presences',
            'recevoir_notifications',
            'verifie_at',
            'revoque_at',
        ])->wherePivot('statut', StatutLienResponsable::Verifie->value)
            ->wherePivotNull('revoque_at')
            ->withTimestamps();
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(
            fn (): string => collect([$this->nom, $this->postnom, $this->prenom])
                ->filter()
                ->implode(' '),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
            'doit_changer_mot_de_passe' => 'boolean',
            'telephone_verifie_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
        ];
    }
}
