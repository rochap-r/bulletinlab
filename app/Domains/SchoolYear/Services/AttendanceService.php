<?php

namespace App\Domains\SchoolYear\Services;

use App\Domains\Student\Exceptions\EnrollmentConflictException;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\PresenceEleve;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class AttendanceService
{
    public function __construct(
        private TenantContext $tenantContext,
        private EstablishmentContext $establishmentContext,
    ) {}

    /**
     * @param  array{
     *     date_presence: string,
     *     statut: string,
     *     moment?: string,
     *     observation?: ?string,
     *     periode_id?: ?int
     * }  $data
     */
    public function recordAttendance(Inscription $inscription, array $data, int $actorId): PresenceEleve
    {
        $moment = $data['moment'] ?? 'JOURNEE';
        $attendance = DB::transaction(function () use ($inscription, $data, $moment, $actorId): PresenceEleve {
            $lockedEnrollment = Inscription::query()
                ->whereKey($inscription->getKey())
                ->where('tenant_id', $this->tenantContext->id())
                ->whereHas(
                    'classe',
                    fn ($query) => $query->where(
                        'etablissement_id',
                        $this->establishmentContext->id(),
                    ),
                )
                ->with('classe')
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEnrollment->statut !== 'ACTIF') {
                throw new EnrollmentConflictException(
                    'Attendance can only be recorded for active enrollments.',
                );
            }

            $values = [
                'tenant_id' => $this->tenantContext->id(),
                'etablissement_id' => $this->establishmentContext->id(),
                'annee_scolaire_id' => $lockedEnrollment->classe->annee_scolaire_id,
                'classe_id' => $lockedEnrollment->classe_id,
                'periode_id' => $data['periode_id'] ?? null,
                'statut' => $data['statut'],
                'observation' => $data['observation'] ?? null,
                'saisi_par' => $actorId,
            ];
            $attendance = PresenceEleve::query()
                ->where('inscription_id', $lockedEnrollment->id)
                ->whereDate('date_presence', $data['date_presence'])
                ->where('moment', $moment)
                ->lockForUpdate()
                ->first();

            if ($attendance) {
                $attendance->update($values);

                return $attendance;
            }

            return PresenceEleve::query()->create([
                'inscription_id' => $lockedEnrollment->id,
                'date_presence' => $data['date_presence'],
                'moment' => $moment,
                ...$values,
            ]);
        }, attempts: 3);

        return $attendance->load(['inscription.eleve', 'saisiPar']);
    }

    public function getAttendance(
        Classe $classe,
        ?string $date = null,
        ?int $periodeId = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        return PresenceEleve::query()
            ->where('classe_id', $classe->id)
            ->where('etablissement_id', $this->establishmentContext->id())
            ->when($date, fn ($query, string $value) => $query->whereDate('date_presence', $value))
            ->when($periodeId, fn ($query, int $value) => $query->where('periode_id', $value))
            ->with(['inscription.eleve', 'saisiPar'])
            ->orderByDesc('date_presence')
            ->orderBy('inscription_id')
            ->paginate($perPage);
    }

    public function getStudentAttendance(
        Inscription $inscription,
        ?string $startDate = null,
        ?string $endDate = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        return PresenceEleve::query()
            ->where('inscription_id', $inscription->id)
            ->where('etablissement_id', $this->establishmentContext->id())
            ->when($startDate, fn ($query, string $value) => $query->whereDate('date_presence', '>=', $value))
            ->when($endDate, fn ($query, string $value) => $query->whereDate('date_presence', '<=', $value))
            ->with(['inscription.eleve', 'saisiPar'])
            ->orderByDesc('date_presence')
            ->paginate($perPage);
    }
}
