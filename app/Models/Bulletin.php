<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bulletin extends Model
{
    use HasTenant;

    protected $table = 'bulletins';
    protected $guarded = [];

    protected $casts = [
        'total_obtenu' => 'decimal:2',
        'total_maxima' => 'decimal:2',
        'pourcentage' => 'decimal:2',
        'date_deliberation' => 'date',
        'verrouille' => 'boolean',
    ];

    public function inscription(): BelongsTo { return $this->belongsTo(Inscription::class); }
    public function classe(): BelongsTo { return $this->belongsTo(Classe::class); }
    public function modeleBulletin(): BelongsTo { return $this->belongsTo(ModeleBulletin::class); }
    public function notes(): HasMany { return $this->hasMany(Note::class); }
}
