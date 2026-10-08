<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Dote un modèle d'un identifiant public `public_id` (ULID de 26
 * caractères, triable), généré automatiquement à la création.
 *
 * Le route model binding public par `public_id` étant différé au schéma
 * cible, ce trait ne change pas la clé de route : le binding actuel reste
 * l'identifiant interne numérique.
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public static function findByPublicId(string $publicId): ?static
    {
        return static::query()->where('public_id', $publicId)->first();
    }
}
