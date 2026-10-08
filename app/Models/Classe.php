<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'classes';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'annee_scolaire_id',
        'cycle_id',
        'degre_id',
        'niveau_id',
        'option_id',
        'system_template_id',
        'parallele',
        'nom',
        'code',
        'titulaire_id',
        'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class, 'annee_scolaire_id');
    }

    public function systemTemplate(): BelongsTo
    {
        return $this->belongsTo(SystemTemplateBulletin::class, 'system_template_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RefCycle::class, 'cycle_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(RefNiveau::class, 'niveau_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(RefOption::class, 'option_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function attributionsCours(): HasMany
    {
        return $this->hasMany(AttributionCours::class);
    }

    public function modeleBulletins(): HasMany
    {
        return $this->hasMany(ModeleBulletin::class);
    }

    public function titulaire(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'titulaire_id');
    }

    public function titulariats(): HasMany
    {
        return $this->hasMany(TitulaireClasse::class);
    }
}
