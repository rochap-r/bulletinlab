<?php

namespace App\Domains\Student\Services;

use App\Domains\Student\Contracts\ImportStudentsServiceInterface;
use App\Domains\Student\Exceptions\StudentException;
use App\Models\Classe;
use App\Models\AnneeScolaire;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Support\Facades\DB;

class ImportStudentsService implements ImportStudentsServiceInterface
{
    public function import(array $data, int $classeId, int $anneeScolaireId): array
    {
        return DB::transaction(function () use ($data, $classeId, $anneeScolaireId) {
            $classe = Classe::findOrFail($classeId);
            $anneeScolaire = AnneeScolaire::findOrFail($anneeScolaireId);

            $created = 0;
            $errors = [];

            foreach ($data as $studentData) {
                try {
                    $eleve = Eleve::updateOrCreate(
                        [
                            'tenant_id' => $classe->tenant_id,
                            'nom' => $studentData['nom'],
                            'postnom' => $studentData['postnom'] ?? null,
                            'prenom' => $studentData['prenom'] ?? null,
                        ],
                        [
                            'etablissement_id' => $classe->etablissement_id,
                            'sexe' => $studentData['sexe'] ?? null,
                            'date_naissance' => $studentData['date_naissance'] ?? null,
                            'lieu_naissance' => $studentData['lieu_naissance'] ?? null,
                            'nom_pere' => $studentData['nom_pere'] ?? null,
                            'nom_mere' => $studentData['nom_mere'] ?? null,
                            'telephone_parent' => $studentData['telephone_parent'] ?? null,
                            'adresse' => $studentData['adresse'] ?? null,
                            'actif' => true,
                        ]
                    );

                    $existingInscription = Inscription::where('eleve_id', $eleve->id)
                        ->where('annee_scolaire_id', $anneeScolaireId)
                        ->first();

                    if (!$existingInscription) {
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
                } catch (\Exception $e) {
                    $errors[] = [
                        'student' => $studentData['nom'] . ' ' . ($studentData['postnom'] ?? ''),
                        'error' => $e->getMessage(),
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
