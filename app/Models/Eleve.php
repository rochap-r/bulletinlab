<?php

namespace App\Models;

use App\Enums\StatutLienResponsable;
use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    protected static function booted(): void
    {
        static::saving(function (Eleve $eleve): void {
            if (! $eleve->isDirty('telephone_parent')) {
                return;
            }

            if (blank($eleve->telephone_parent)) {
                $eleve->telephone_parent_normalise = null;

                return;
            }

            try {
                $eleve->telephone_parent_normalise = PhoneNumberNormalizer::normalize(
                    $eleve->telephone_parent,
                );
            } catch (\InvalidArgumentException) {
                $eleve->telephone_parent_normalise = null;
            }
        });
    }

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

    public function liensResponsables(): HasMany
    {
        return $this->hasMany(LienResponsableEleve::class);
    }

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(
            Responsable::class,
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
