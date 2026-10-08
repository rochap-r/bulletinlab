<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemTemplateNode extends Model
{
    protected $table = 'system_template_nodes';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(RefMatiere::class, 'matiere_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('ordre');
    }

    public function maxima(): HasMany
    {
        return $this->hasMany(SystemTemplateMaximum::class, 'node_id')->orderBy('periode_id');
    }
}
