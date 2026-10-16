<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Teacher\Services\CourseAssignmentSpreadsheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teacher\CourseAssignmentSpreadsheetRequest;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CourseAssignmentSpreadsheetController extends Controller
{
    public function template(CourseAssignmentSpreadsheetRequest $request, CourseAssignmentSpreadsheetService $service): StreamedResponse
    {
        $book = $service->template($request->schoolClass());

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'attributions-cours.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function preview(CourseAssignmentSpreadsheetRequest $request, CourseAssignmentSpreadsheetService $service): JsonResponse
    {
        return response()->json(['data' => $service->preview($request->schoolClass(), $request->file('fichier'))]);
    }
}
