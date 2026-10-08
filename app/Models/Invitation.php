<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $table = 'invitations';

    protected $fillable = [
        'tenant_id',
        'etablissement_id',
        'email',
        'role_id',
        'token_hash',
        'expire_at',
        'accepted_at',
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'expire_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function etablissement(): BelongsTo
    {
        return $this->belongsTo(Etablissement::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'created_by');
    }

    public function statut(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'ACCEPTEE',
            $this->revoked_at !== null => 'REVOQUEE',
            $this->expire_at !== null && $this->expire_at->isPast() => 'EXPIREE',
            default => 'EN_ATTENTE',
        };
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && ($this->expire_at === null || $this->expire_at->isFuture());
    }
}
