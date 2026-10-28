<?php

namespace App\Domains\Responsable\Services;

use App\Enums\StatutLienResponsable;
use App\Enums\TypeLienResponsable;
use App\Models\AuditLog;
use App\Models\Eleve;
use App\Models\LienResponsableEleve;
use App\Models\Responsable;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ResponsableStudentLinkSyncService
{
    private const VERIFICATION_METHOD = 'DOSSIER_ELEVE';

    public function __construct(private Hasher $hasher) {}

    public function syncForPhone(string $normalizedPhone): ?Responsable
    {
        $students = Eleve::withoutGlobalScopes()
            ->where('telephone_parent_normalise', $normalizedPhone)
            ->orderBy('id')
            ->get();

        foreach ($students as $student) {
            $this->syncStudent($student);
        }

        return Responsable::query()
            ->where('telephone_normalise', $normalizedPhone)
            ->first();
    }

    public function syncStudent(Eleve $student): void
    {
        DB::transaction(function () use ($student): void {
            $lockedStudent = Eleve::withoutGlobalScopes()
                ->whereKey($student->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $normalizedPhone = $lockedStudent->telephone_parent_normalise;
            $automaticLinks = LienResponsableEleve::withoutGlobalScopes()
                ->where('eleve_id', $lockedStudent->getKey())
                ->where('methode_verification', self::VERIFICATION_METHOD)
                ->lockForUpdate()
                ->get();

            if (blank($normalizedPhone)) {
                $this->revokeObsoleteLinks($automaticLinks);

                return;
            }

            $responsable = $this->resolveResponsable($lockedStudent, $normalizedPhone);

            $this->revokeObsoleteLinks(
                $automaticLinks->where('responsable_id', '!=', $responsable->getKey()),
            );

            $link = LienResponsableEleve::withoutGlobalScopes()
                ->firstOrNew([
                    'responsable_id' => $responsable->getKey(),
                    'eleve_id' => $lockedStudent->getKey(),
                ]);

            $wasNew = ! $link->exists;
            $link->forceFill([
                'tenant_id' => $lockedStudent->tenant_id,
                'type_lien' => $wasNew ? TypeLienResponsable::Tuteur : $link->type_lien,
                'statut' => StatutLienResponsable::Verifie,
                'principal' => $wasNew ? true : $link->principal,
                'peut_consulter_notes' => true,
                'peut_consulter_presences' => true,
                'recevoir_notifications' => true,
                'verifie_at' => $link->verifie_at ?? now(),
                'methode_verification' => $link->methode_verification ?? self::VERIFICATION_METHOD,
                'revoque_par' => null,
                'revoque_at' => null,
                'motif_revocation' => null,
            ])->save();

            if ($wasNew || $link->wasChanged()) {
                AuditLog::query()->create([
                    'tenant_id' => $lockedStudent->tenant_id,
                    'responsable_id' => $responsable->getKey(),
                    'action' => 'RESPONSABLE_LIEN_SYNCHRONISE',
                    'table_cible' => $link->getTable(),
                    'ligne_cible_id' => $link->getKey(),
                    'nouvelles_valeurs' => [
                        'statut' => StatutLienResponsable::Verifie->value,
                        'methode' => self::VERIFICATION_METHOD,
                    ],
                ]);
            }
        }, attempts: 3);
    }

    private function resolveResponsable(Eleve $student, string $normalizedPhone): Responsable
    {
        if (! Responsable::query()->where('telephone_normalise', $normalizedPhone)->exists()) {
            $now = now();
            $displayName = $student->nom_pere
                ?: ($student->nom_mere ?: 'Responsable '.$student->nom);

            Responsable::query()->insertOrIgnore([
                'public_id' => (string) Str::ulid(),
                'nom' => Str::limit($displayName, 100, ''),
                'telephone' => $normalizedPhone,
                'telephone_normalise' => $normalizedPhone,
                'mot_de_passe' => $this->hasher->make(
                    (string) config('responsables.default_password', '123456'),
                ),
                'doit_changer_mot_de_passe' => true,
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $responsable = Responsable::query()
            ->where('telephone_normalise', $normalizedPhone)
            ->lockForUpdate()
            ->firstOrFail();

        if (blank($responsable->mot_de_passe)) {
            $responsable->forceFill([
                'mot_de_passe' => $this->hasher->make(
                    (string) config('responsables.default_password', '123456'),
                ),
                'doit_changer_mot_de_passe' => true,
            ])->save();
        }

        return $responsable;
    }

    private function revokeObsoleteLinks(iterable $links): void
    {
        foreach ($links as $link) {
            if ($link->statut === StatutLienResponsable::Revoque) {
                continue;
            }

            $link->forceFill([
                'statut' => StatutLienResponsable::Revoque,
                'revoque_at' => now(),
                'motif_revocation' => 'Numéro du responsable remplacé dans le dossier élève.',
            ])->save();
        }
    }
}
