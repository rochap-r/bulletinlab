<?php

namespace App\Http\Controllers\Api\V1\Membership;

use App\Domains\Membership\Services\ImportEnseignantsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Membership\ImportEnseignantsRequest;
use App\Models\Utilisateur;
use Illuminate\Http\JsonResponse;

final class ImportEnseignantsController extends Controller
{
    public function __construct(private readonly ImportEnseignantsService $service) {}

    public function __invoke(ImportEnseignantsRequest $request): JsonResponse
    {
        /** @var Utilisateur $actor */
        $actor = $request->user();

        $inputRows = $request->enseignantsRows();
        $rows = array_map(
            fn (array $row, int $index): array => ['ligne' => $index + 1, 'values' => $row],
            $inputRows,
            array_keys($inputRows),
        );

        $report = $this->service->import(
            $rows,
            $request->integer('etablissement_id') ?: null,
            $actor,
        );

        return response()->json(['data' => $report], 201);
    }
}
