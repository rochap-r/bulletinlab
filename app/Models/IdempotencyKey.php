<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registre d'idempotence des commandes synchrones réessayables.
 *
 * Une même clé avec le même hash de requête retourne le résultat initial ;
 * la même clé avec un payload différent retourne un conflit 409.
 */
class IdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'context_type',
        'context_id',
        'actor_type',
        'actor_id',
        'command_name',
        'key',
        'request_hash',
        'result_type',
        'result_id',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'context_id' => 'integer',
        'actor_id' => 'integer',
        'result_id' => 'integer',
        'status' => 'integer',
        'expires_at' => 'immutable_datetime',
    ];

    /** @return MorphTo<Model, $this> */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
