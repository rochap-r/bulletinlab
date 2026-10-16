<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\SaveClassTitularAssignmentsService;
use App\Domains\Teacher\Services\TitularAssignmentSpreadsheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\TitularAssignmentRequest;
use App\Http\Resources\Api\V1\ClasseResource;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TitularAssignmentController extends Controller
{
    public function save(TitularAssignmentRequest $request, SaveClassTitularAssignmentsService $service): JsonResponse
    {
        $classes = $service->save($request->school(), $request->actor(), $request->integer('annee_scolaire_id'), $request->validated('titulariats'));

        return ClasseResource::collection($classes)->response();
    }

    public function template(TitularAssignmentRequest $request, TitularAssignmentSpreadsheetService $service): StreamedResponse
    {
        $book = $service->template($request->school(), $request->integer('annee_scolaire_id'));

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'titulariats.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function preview(TitularAssignmentRequest $request, TitularAssignmentSpreadsheetService $service): JsonResponse
    {
        return response()->json(['data' => $service->preview($request->school(), $request->integer('annee_scolaire_id'), $request->file('fichier'))]);
    }
}
