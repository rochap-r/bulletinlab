<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Eleve extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'eleves';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'num_permanent',
        'nom',
        'postnom',
        'prenom',
        'sexe',
        'lieu_naissance',
        'date_naissance',
        'nom_pere',
        'nom_mere',
        'telephone_parent',
        'adresse',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function activeInscription(): HasOne
    {
        return $this->hasOne(Inscription::class)
            ->where('statut', 'ACTIF')
            ->latestOfMany();
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
