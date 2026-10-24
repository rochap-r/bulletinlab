<?php

namespace App\Domains\Membership\Services;

use App\Domains\Membership\DTOs\CreateDirectUtilisateurData;
use App\Models\Utilisateur;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as PhpSpreadsheetDate;

/**
 * Import mutualisé des enseignants : chaque ligne crée un utilisateur
 * avec le rôle ENSEIGNANT. Le titulariat est nommé par classe (préfet /
 * directeur des études) via PUT /classes/{classe}/titulariat.
 */
final class ImportEnseignantsService
{
    public function __construct(private readonly DirectUtilisateurService $service) {}

    /**
     * @param  list<array{ligne: int, values: array<string, mixed>}>  $rows
     * @return array{crees: list<array<string, mixed>>, erreurs: list<array<string, mixed>>, total: int, crees_count: int, erreurs_count: int}
     */
    public function import(array $rows, ?int $etablissementId, Utilisateur $actor): array
    {
        $crees = [];
        $erreurs = [];
        $telephonesVus = [];
        $matriculesVus = [];

        foreach ($rows as $row) {
            $ligne = $row['ligne'];
            $values = $row['values'];

            try {
                $normalise = $this->rowData($values, $etablissementId, $ligne, $telephonesVus, $matriculesVus);
                $telephonesVus[] = $normalise->telephone;
                if ($normalise->matricule !== null) {
                    $matriculesVus[] = mb_strtolower($normalise->matricule);
                }

                $resultat = $this->service->create($normalise, $actor);
                $crees[] = [
                    'ligne' => $ligne,
                    'id' => $resultat->utilisateur->getKey(),
                    'nom' => trim(implode(' ', array_filter([$resultat->utilisateur->prenom, $resultat->utilisateur->nom, $resultat->utilisateur->postnom]))),
                    'telephone' => $resultat->utilisateur->telephone_normalise,
                    'mot_de_passe_temporaire' => $resultat->motDePasseTemporaire,
                ];
            } catch (ValidationException $exception) {
                $erreurs[] = [
                    'ligne' => $ligne,
                    'messages' => collect($exception->errors())->flatten()->values()->all(),
                ];
            }
        }

        return [
            'crees' => $crees,
            'erreurs' => $erreurs,
            'total' => count($rows),
            'crees_count' => count($crees),
            'erreurs_count' => count($erreurs),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $telephonesVus
     * @param  array<int, string>  $matriculesVus
     */
    private function rowData(array $row, ?int $etablissementId, int $ligne, array $telephonesVus, array $matriculesVus): CreateDirectUtilisateurData
    {
        $telephoneBrut = trim((string) ($row['telephone'] ?? ''));

        try {
            $telephone = PhoneNumberNormalizer::normalize($telephoneBrut);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                "enseignants.{$ligne}.telephone" => ["Ligne {$ligne} : téléphone invalide (format international +243... attendu)."],
            ]);
        }

        if (in_array($telephone, $telephonesVus, true)) {
            throw ValidationException::withMessages([
                "enseignants.{$ligne}.telephone" => ["Ligne {$ligne} : téléphone dupliqué dans le fichier."],
            ]);
        }

        $matriculeBrut = isset($row['matricule']) && trim((string) $row['matricule']) !== '' ? trim((string) $row['matricule']) : null;

        if ($matriculeBrut !== null) {
            if (in_array(mb_strtolower($matriculeBrut), $matriculesVus, true)) {
                throw ValidationException::withMessages([
                    "enseignants.{$ligne}.matricule" => ["Ligne {$ligne} : matricule dupliqué dans le fichier."],
                ]);
            }
            $matricule = $matriculeBrut;
        } else {
            do {
                $matricule = 'ENS-'.strtoupper(Str::random(6));
            } while (in_array(mb_strtolower($matricule), $matriculesVus, true));
        }

        $sexe = isset($row['sexe']) && trim((string) $row['sexe']) !== '' ? mb_strtoupper(trim((string) $row['sexe'])) : null;

        // L'import ne crée que des enseignants : la colonne `role` historique
        // est ignorée et le titulariat est nommé par classe (préfet /
        // directeur des études) via PUT /classes/{classe}/titulariat.
        $role = 'ENSEIGNANT';

        $payload = [
            'nom' => trim((string) ($row['nom'] ?? '')),
            'postnom' => $this->cleanOrNull($row['postnom'] ?? null),
            'prenom' => $this->cleanOrNull($row['prenom'] ?? null),
            'sexe' => $sexe,
            'date_naissance' => $this->normalizeDate($this->cleanOrNull($row['date_naissance'] ?? null)),
            'lieu_naissance' => $this->cleanOrNull($row['lieu_naissance'] ?? null),
            'email' => $this->cleanOrNull($row['email'] ?? null),
            'telephone' => $telephone,
            'adresse' => $this->cleanOrNull($row['adresse'] ?? null),
            'matricule' => $matricule,
            'qualification' => $this->cleanOrNull($row['qualification'] ?? null),
            'specialite' => $this->cleanOrNull($row['specialite'] ?? null),
            'date_embauche' => $this->normalizeDate($this->cleanOrNull($row['date_embauche'] ?? null)),
            'role' => $role,
        ];

        $validator = Validator::make($payload, [
            'nom' => ['required', 'string', 'max:100'],
            'postnom' => ['nullable', 'string', 'max:100'],
            'prenom' => ['nullable', 'string', 'max:100'],
            'sexe' => ['nullable', 'in:M,F'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'lieu_naissance' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'telephone' => ['required', 'string', 'max:16'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'specialite' => ['nullable', 'string', 'max:150'],
            'date_embauche' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return new CreateDirectUtilisateurData(
            nom: $payload['nom'],
            postnom: $payload['postnom'],
            prenom: $payload['prenom'],
            email: $payload['email'],
            telephone: $payload['telephone'],
            role: $payload['role'],
            etablissementId: $etablissementId,
            sexe: $payload['sexe'],
            dateNaissance: $payload['date_naissance'],
            lieuNaissance: $payload['lieu_naissance'],
            adresse: $payload['adresse'],
            matricule: $payload['matricule'],
            qualification: $payload['qualification'],
            specialite: $payload['specialite'],
            dateEmbauche: $payload['date_embauche'],
        );
    }

    private function cleanOrNull(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value) || is_float($value)) {
            $string = is_float($value) && floor($value) === $value
                ? number_format($value, 0, '', '')
                : (string) $value;

            return trim($string) === '' ? null : trim($string);
        }

        if (! is_string($value)) {
            return null;
        }

        $cleaned = trim($value);

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Normalise une valeur de date lue depuis Excel vers le format Y-m-d
     * attendu par la règle de validation Laravel `date`.
     *
     * Gère :
     *  - le format Y-m-d (déjà valide),
     *  - les formats européens d/m/Y, d-m-Y, d.m.Y,
     *  - les numéros de série Excel (dates stockées en nombre).
     */
    private function normalizeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Déjà au format Y-m-d attendu par la validation Laravel.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Numéro de série Excel : date stockée en nombre dans le classeur.
        if (ctype_digit($value)) {
            $serial = (int) $value;
            if ($serial >= 30000 && $serial <= 60000) {
                $timestamp = PhpSpreadsheetDate::excelToTimestamp($serial);
                if ($timestamp !== false && $timestamp > 0) {
                    return date('Y-m-d', $timestamp);
                }
            }
        }

        // Formats européens courants : d/m/Y, d-m-Y, d.m.Y
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $value)) {
            $date = \DateTime::createFromFormat('d/m/Y', $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }

        return $value;
    }
}
