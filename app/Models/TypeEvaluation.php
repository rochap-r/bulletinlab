<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeEvaluation extends Model
{
    protected $table = 'types_evaluations';

    protected $fillable = [
        'code',
        'nom',
        'description',
        'actif',
    ];

    protected $casts = [
        'actif' => 'boolean',
    ];

    protected $attributes = [
        'actif' => true,
    ];

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getKey(),
            'code' => $this->code,
            'nom' => $this->nom,
            'description' => $this->description,
            'actif' => (bool) $this->actif,
        ];
    }
}
