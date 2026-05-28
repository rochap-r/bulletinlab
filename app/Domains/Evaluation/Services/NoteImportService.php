<?php

namespace App\Domains\Evaluation\Services;

use App\Domains\Evaluation\Contracts\NoteEntryServiceInterface;
use App\Domains\Evaluation\Services\NoteValidationService;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Http\UploadedFile;

class NoteImportService
{
    public function __construct(
        private NoteEntryServiceInterface $noteService,
        private NoteValidationService $validationService
    ) {}

    public function import(UploadedFile $file, int $classeId, int $nodeId, int $periodeId): array
    {
        $this->validateFile($file);

        $data = $this->parseFile($file);

        $results = [
            'success' => 0,
            'errors' => [],
        ];

        foreach ($data as $row) {
            try {
                $this->validateAndImportRow($row, $classeId, $nodeId, $periodeId);
                $results['success']++;
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'row' => $row['row_number'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        app(EncodageProgressService::class)->updateProgress($classeId, $nodeId, $periodeId);

        return $results;
    }

    private function validateFile(UploadedFile $file): void
    {
        if (!in_array($file->getClientOriginalExtension(), ['xlsx', 'xls', 'csv'])) {
            throw new \Exception('Format de fichier non supporté. Utilisez xlsx, xls ou csv.');
        }
    }

    private function parseFile(UploadedFile $file): array
    {
        $data = [];
        $handle = fopen($file->getPathname(), 'r');

        $row = 0;
        while (($line = fgetcsv($handle, 1000, ',')) !== false) {
            $row++;
            if ($row === 1) continue;

            if (empty($line[0]) || empty($line[1])) {
                continue;
            }

            $data[] = [
                'row_number' => $row,
                'matricule' => $line[0],
                'note' => (float) $line[1],
            ];
        }

        fclose($handle);

        return $data;
    }

    private function validateAndImportRow(array $row, int $classeId, int $nodeId, int $periodeId): void
    {
        $eleve = Eleve::where('num_permanent', $row['matricule'])
            ->orWhere('matricule', $row['matricule'])
            ->first();

        if (!$eleve) {
            throw new \Exception("Élève non trouvé avec le matricule: {$row['matricule']}");
        }

        $inscription = Inscription::where('eleve_id', $eleve->id)
            ->where('classe_id', $classeId)
            ->first();

        if (!$inscription) {
            throw new \Exception("L'élève n'est pas inscrit dans cette classe.");
        }

        $this->validationService->validateMaximum($nodeId, $periodeId, $classeId, $row['note']);

        $this->noteService->saveNote([
            'bulletin_id' => $inscription->bulletin->id ?? null,
            'node_id' => $nodeId,
            'periode_id' => $periodeId,
            'valeur' => $row['note'],
        ]);
    }
}
