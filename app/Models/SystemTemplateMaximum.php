<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTemplateMaximum extends Model
{
    protected $table = 'system_template_maxima';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maxima' => 'float',
            'coefficient' => 'float',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(SystemTemplateNode::class, 'node_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(SystemTemplatePeriode::class, 'periode_id');
    }
}
