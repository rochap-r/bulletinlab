<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SuiviEncodageNote extends Model
{
    use HasTenant;

    protected $table = 'suivis_encodage_notes';

    protected $fillable = [
        'tenant_id',
        'classe_id',
        'modele_bulletin_id',
        'node_id',
        'periode_id',
        'enseignant_id',
        'nombre_eleves',
        'nombre_notes_saisies',
        'pourcentage_avancement',
        'statut',
        'derniere_saisie_at',
    ];

    protected $casts = [
        'pourcentage_avancement' => 'decimal:2',
        'derniere_saisie_at' => 'datetime',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(ModeleNode::class, 'node_id');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'enseignant_id');
    }
}
