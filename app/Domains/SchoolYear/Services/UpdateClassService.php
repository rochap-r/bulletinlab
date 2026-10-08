<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\ClassArchiveConflictException;
use App\Models\Classe;
use Illuminate\Support\Facades\DB;

final class UpdateClassService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Classe $class, array $attributes): Classe
    {
        return DB::transaction(function () use ($class, $attributes): Classe {
            $lockedClass = Classe::query()
                ->whereKey($class->getKey())
                ->where('tenant_id', $class->tenant_id)
                ->where('etablissement_id', $class->etablissement_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedClass->active) {
                throw new ClassArchiveConflictException(
                    'Archived classes cannot be modified.',
                );
            }

            $lockedClass->update($attributes);

            return $lockedClass->refresh();
        }, attempts: 3);
    }
}
