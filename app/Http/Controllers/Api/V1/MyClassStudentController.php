<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Student\Services\ManageMyClassStudentsService;
use App\Domains\Student\Services\StudentSpreadsheetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\MyClassStudentRequest;
use App\Http\Resources\Api\V1\InscriptionResource;
use App\Models\Inscription;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MyClassStudentController extends Controller
{
    public function __construct(private ManageMyClassStudentsService $students, private StudentSpreadsheetService $spreadsheets) {}

    public function index(MyClassStudentRequest $request): JsonResponse
    {
        $class = $request->schoolClass();
        $search = trim((string) $request->input('recherche', ''));
        $perPage = (int) $request->input('per_page', 25);
        if (! in_array($perPage, [25, 50, 100], true)) {
            $perPage = 25;
        }

        $rows = Inscription::query()->where('classe_id', $class->id)->where('annee_scolaire_id', $class->annee_scolaire_id)
            ->whereHas('eleve', function ($query) use ($class, $search): void {
                $query->where('etablissement_id', $class->etablissement_id);
                if ($search !== '') {
                    $query->where(fn ($names) => $names->where('nom', 'like', "%$search%")
                        ->orWhere('postnom', 'like', "%$search%")->orWhere('prenom', 'like', "%$search%")
                        ->orWhere('num_permanent', 'like', "%$search%"));
                }
            })->with('eleve')->latest('id')->paginate($perPage)->withQueryString();

        return InscriptionResource::collection($rows)->response();
    }

    public function store(MyClassStudentRequest $request): JsonResponse
    {
        return InscriptionResource::collection($this->students->create($request->schoolClass(), $request->user(), $request->validated('eleves')))
            ->response()->setStatusCode(201);
    }

    public function show(MyClassStudentRequest $request, int $classe, int $inscription): JsonResponse
    {
        return InscriptionResource::make($this->students->enrollment($request->schoolClass(), $inscription))->response();
    }

    public function update(MyClassStudentRequest $request, int $classe, int $inscription): JsonResponse
    {
        return InscriptionResource::make($this->students->update($request->schoolClass(), $request->user(), $inscription, $request->validated()))->response();
    }

    public function destroy(MyClassStudentRequest $request, int $classe, int $inscription): JsonResponse
    {
        return InscriptionResource::make($this->students->remove($request->schoolClass(), $request->user(), $inscription, $request->validated('motif')))->response();
    }

    public function template(MyClassStudentRequest $request): StreamedResponse
    {
        $book = $this->spreadsheets->template($request->schoolClass());

        return response()->streamDownload(function () use ($book): void {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'modele-eleves.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function preview(MyClassStudentRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->spreadsheets->preview($request->schoolClass(), $request->file('fichier'))]);
    }
}
