<?php

namespace App\Models;

use App\Domains\Template\Exceptions\ConfigurationBulletinConflictException;
use App\Enums\TemplateStatut;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemTemplateBulletin extends Model
{
    protected $table = 'system_templates_bulletin';

    protected $guarded = [];

    protected $casts = [
        'actif' => 'boolean',
        'statut' => TemplateStatut::class,
        'publie_a' => 'datetime',
    ];

    public function scopeWithComposition(Builder $query): Builder
    {
        return $query->with([
            'cycle',
            'degre',
            'option',
            'niveauMin',
            'niveauMax',
            'layout',
            'nodes.matiere',
            'nodes.maxima',
            'periodes',
            'maxima',
            'blocs.bloc',
        ]);
    }

    public function isEditable(): bool
    {
        return in_array($this->statut, [TemplateStatut::BROUILLON, TemplateStatut::VALIDE], true);
    }

    public function assertEditable(): void
    {
        if (! $this->isEditable()) {
            throw new ConfigurationBulletinConflictException(
                'Cette configuration est verrouillée (publiée ou dépréciée) et ne peut plus être modifiée.',
            );
        }
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(RefCycle::class, 'cycle_id');
    }

    public function degre(): BelongsTo
    {
        return $this->belongsTo(RefDegre::class, 'degre_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(RefOption::class, 'option_id');
    }

    public function niveauMin(): BelongsTo
    {
        return $this->belongsTo(RefNiveau::class, 'niveau_min_id');
    }

    public function niveauMax(): BelongsTo
    {
        return $this->belongsTo(RefNiveau::class, 'niveau_max_id');
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(SystemLayoutBulletin::class, 'layout_id');
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(SystemTemplateNode::class, 'template_id')->orderBy('ordre');
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(SystemTemplatePeriode::class, 'template_id')->orderBy('ordre');
    }

    public function maxima(): HasMany
    {
        return $this->hasMany(SystemTemplateMaximum::class, 'template_id');
    }

    public function blocs(): HasMany
    {
        return $this->hasMany(SystemTemplateBloc::class, 'template_id')->orderBy('ordre');
    }
}
