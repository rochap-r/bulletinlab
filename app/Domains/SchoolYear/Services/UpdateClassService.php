<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\SchoolYear\Exceptions\ClassArchiveConflictException;
use App\Domains\Teacher\Services\SynchronizePrimaryClassCourseAssignmentsService;
use App\Models\Classe;
use App\Models\Utilisateur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpdateClassService
{
    public function __construct(
        private SynchronizePrimaryClassCourseAssignmentsService $synchronizePrimaryCourses,
    ) {}

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

            $previousTitularId = $lockedClass->titulaire_id;

            $lockedClass->update($attributes);

            // Pour une classe primaire, synchroniser automatiquement les cours
            // avec le nouveau titulaire dès qu'il est nommé.
            if (filled($attributes['titulaire_id'] ?? null)
                && $attributes['titulaire_id'] !== $previousTitularId
                && $lockedClass->isPrimarySchool()) {

                $titular = Utilisateur::query()->find($attributes['titulaire_id']);
                if ($titular) {
                    $today = Carbon::today(config('app.business_timezone'))->toDateString();
                    $this->synchronizePrimaryCourses->synchronize(
                        $lockedClass,
                        $titular,
                        $today,
                        null,
                    );
                }
            }

            return $lockedClass->refresh();
        }, attempts: 3);
    }
}
