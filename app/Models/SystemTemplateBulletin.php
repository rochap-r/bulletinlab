<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemTemplateBulletin extends Model
{
    protected $table = 'system_templates_bulletin';
    protected $guarded = [];

    protected $casts = ['actif' => 'boolean'];

    public function layout(): BelongsTo { return $this->belongsTo(SystemLayoutBulletin::class, 'layout_id'); }
    public function nodes(): HasMany { return $this->hasMany(SystemTemplateNode::class, 'template_id')->orderBy('ordre'); }
    public function periodes(): HasMany { return $this->hasMany(SystemTemplatePeriode::class, 'template_id')->orderBy('ordre'); }
    public function maxima(): HasMany { return $this->hasMany(SystemTemplateMaximum::class, 'template_id'); }
    public function blocs(): HasMany { return $this->hasMany(SystemTemplateBloc::class, 'template_id'); }
}
