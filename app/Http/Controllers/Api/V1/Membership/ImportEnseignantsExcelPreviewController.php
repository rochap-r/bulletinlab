<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\ImportEnseignantsService;
use App\Domains\Membership\Services\ReadTeacherSpreadsheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\ImportEnseignantsExcelRequest;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

final class ImportEnseignantsExcelPreviewController extends Controller
{
    public function __construct(
        private readonly ReadTeacherSpreadsheetService $reader,
        private readonly ImportEnseignantsService $service,
    ) {}

    public function __invoke(
        ImportEnseignantsExcelRequest $request,
    ): JsonResponse {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        /** @var UploadedFile $file */
        $file = $request->file('fichier');

        $rows = $this->reader->read($file);

        $etablissementId = $request->integer('etablissement_id') ?: null;

        return response()->json([
            'data' => $this->service->preview($rows, $etablissementId),
        ])->header('Cache-Control', 'no-store, private');
    }
}
