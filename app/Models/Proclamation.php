<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use App\Models\Concerns\HasTenant;
use Illuminate\Database\Eloquent\Model;

class Proclamation extends Model
{
    use HasPublicId;
    use HasTenant;

    protected $table = 'proclamations';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'annee_scolaire_id',
        'classe_id',
        'periode_id',
        'publication_id',
        'type',
        'statut',
        'date_generation',
        'generee_par',
        'validee_par',
        'validee_at',
        'pdf_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'date_generation' => 'date',
        'validee_at' => 'datetime',
    ];
}
