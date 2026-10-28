<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $fillable = [
        'tenant_id',
        'utilisateur_id',
        'responsable_id',
        'action',
        'table_cible',
        'ligne_cible_id',
        'anciennes_valeurs',
        'nouvelles_valeurs',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'anciennes_valeurs' => 'array',
        'nouvelles_valeurs' => 'array',
    ];

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Responsable::class);
    }
}
