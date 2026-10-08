<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bulletin extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'bulletins';

    protected $fillable = [
        'tenant_id',
        'inscription_id',
        'classe_id',
        'modele_bulletin_id',
        'id_officiel_document',
        'total_obtenu',
        'total_maxima',
        'pourcentage',
        'mention',
        'decision_proposee',
        'rang',
        'nombre_eleves_classe',
        'decision_statut',
        'lieu_deliberation',
        'date_deliberation',
        'remarque_finale',
        'pdf_path',
        'verrouille',
        'calcule_at',
        'publie_at',
    ];

    protected $casts = [
        'total_obtenu' => 'decimal:2',
        'total_maxima' => 'decimal:2',
        'pourcentage' => 'decimal:2',
        'date_deliberation' => 'date',
        'calcule_at' => 'datetime',
        'publie_at' => 'datetime',
        'verrouille' => 'boolean',
    ];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function modeleBulletin(): BelongsTo
    {
        return $this->belongsTo(ModeleBulletin::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }
}
