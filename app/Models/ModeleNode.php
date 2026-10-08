<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeleNode extends Model
{
    protected $table = 'modele_nodes';

    protected $fillable = [
        'tenant_id',
        'modele_bulletin_id',
        'system_node_id',
        'parent_id',
        'type_node',
        'matiere_id',
        'libelle',
        'ordre',
        'afficher_ligne',
        'calculer_sous_total',
        'obligatoire',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'afficher_ligne' => 'boolean',
        'calculer_sous_total' => 'boolean',
        'obligatoire' => 'boolean',
    ];

    public function modeleBulletin(): BelongsTo
    {
        return $this->belongsTo(ModeleBulletin::class);
    }

    public function maxima(): HasMany
    {
        return $this->hasMany(ModeleMaximum::class, 'node_id');
    }

    public function attributionsCours(): HasMany
    {
        return $this->hasMany(AttributionCours::class, 'node_id');
    }
}
