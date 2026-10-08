<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeleBulletin extends Model
{
    use HasPublicId;

    protected $table = 'modeles_bulletin';

    protected $fillable = [
        'tenant_id',
        'classe_id',
        'annee_scolaire_id',
        'system_template_id',
        'layout_id',
        'nom',
        'type_decoupage',
        'total_general_maxima',
        'verrouille',
        'actif',
    ];

    protected $casts = [
        'total_general_maxima' => 'decimal:2',
        'verrouille' => 'boolean',
        'actif' => 'boolean',
    ];

    public function nodes(): HasMany
    {
        return $this->hasMany(ModeleNode::class)->orderBy('ordre');
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(ModelePeriode::class)->orderBy('ordre');
    }

    public function maxima(): HasMany
    {
        return $this->hasMany(ModeleMaximum::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }
}
