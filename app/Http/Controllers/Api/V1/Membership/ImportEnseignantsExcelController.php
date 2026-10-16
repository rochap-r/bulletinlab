<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Exceptions\TeacherImportException;
use App\Domains\Membership\Services\ImportEnseignantsService;
use App\Domains\Membership\Services\ReadTeacherSpreadsheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\ImportEnseignantsExcelRequest;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class ImportEnseignantsExcelController extends Controller
{
    public function __construct(
        private readonly ReadTeacherSpreadsheetService $reader,
        private readonly ImportEnseignantsService $service,
    ) {}

    public function __invoke(ImportEnseignantsExcelRequest $request): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('fichier');

        try {
            $rows = $this->reader->read($file);
        } catch (TeacherImportException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['fichier' => [$exception->getMessage()]],
            ], 422);
        }

        $report = $this->service->import(
            $rows,
            $request->integer('etablissement_id') ?: null,
            $actor,
        );

        return response()->json(['data' => $report], 201);
    }
}
