<?php

namespace App\Models;

use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rectification extends Model
{
    use HasTenant;

    protected $table = 'rectifications';

    protected $fillable = [
        'tenant_id',
        'publication_id',
        'nouvelle_publication_id',
        'motif',
        'rectifiee_par',
    ];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'publication_id');
    }

    public function nouvellePublication(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'nouvelle_publication_id');
    }
}
