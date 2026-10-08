<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\Contracts\ImportStudentsServiceInterface;
use App\Domains\Student\Exceptions\StudentException;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportStudentsService implements ImportStudentsServiceInterface
{
    public function import(array $data, int $classeId, int $anneeScolaireId): array
    {
        return DB::transaction(function () use ($data, $classeId, $anneeScolaireId) {
            $classe = Classe::query()
                ->lockForUpdate()
                ->findOrFail($classeId);

            if (! $classe->active) {
                throw new StudentException(
                    'The archived class is unavailable for student import.',
                );
            }

            $anneeScolaire = AnneeScolaire::withoutGlobalScopes()
                ->findOrFail($anneeScolaireId);

            $created = 0;
            $errors = [];

            foreach ($data as $studentData) {
                try {
                    $studentIdentity = [
                        'tenant_id' => $classe->tenant_id,
                        'etablissement_id' => $classe->etablissement_id,
                        'nom' => $studentData['nom'],
                        'postnom' => $studentData['postnom'] ?? null,
                        'prenom' => $studentData['prenom'] ?? null,
                    ];

                    $existingStudent = Eleve::query()
                        ->where($studentIdentity)
                        ->lockForUpdate()
                        ->first();

                    if ($existingStudent && ! $existingStudent->actif) {
                        throw new StudentException(
                            'The archived student must be reactivated before enrollment.',
                        );
                    }

                    $eleve = Eleve::updateOrCreate(
                        $studentIdentity,
                        [
                            'sexe' => $studentData['sexe'] ?? null,
                            'date_naissance' => $studentData['date_naissance'] ?? null,
                            'lieu_naissance' => $studentData['lieu_naissance'] ?? null,
                            'nom_pere' => $studentData['nom_pere'] ?? null,
                            'nom_mere' => $studentData['nom_mere'] ?? null,
                            'telephone_parent' => $studentData['telephone_parent'] ?? null,
                            'adresse' => $studentData['adresse'] ?? null,
                        ]
                    );

                    $existingInscription = Inscription::where('eleve_id', $eleve->id)
                        ->where('annee_scolaire_id', $anneeScolaireId)
                        ->first();

                    if (! $existingInscription) {
                        Inscription::create([
                            'tenant_id' => $classe->tenant_id,
                            'eleve_id' => $eleve->id,
                            'classe_id' => $classeId,
                            'annee_scolaire_id' => $anneeScolaireId,
                            'numero_inscription' => $studentData['numero_inscription'] ?? null,
                            'statut' => 'ACTIF',
                            'date_inscription' => now(),
                        ]);
                        $created++;
                    }
                } catch (StudentException $exception) {
                    $errors[] = [
                        'student' => $studentData['nom'].' '.($studentData['postnom'] ?? ''),
                        'error' => $exception->getMessage(),
                    ];
                } catch (Throwable) {
                    $errors[] = [
                        'student' => $studentData['nom'].' '.($studentData['postnom'] ?? ''),
                        'error' => 'The student could not be imported.',
                    ];
                }
            }

            return [
                'created' => $created,
                'errors' => $errors,
            ];
        });
    }
}
