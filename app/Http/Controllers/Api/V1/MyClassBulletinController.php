<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Bulletin\Services\BulletinCompositionService;
use App\Domains\Student\Services\ManageMyClassStudentsService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Student\MyClassStudentRequest;
use App\Http\Resources\Api\V1\TeacherBulletinResource;
use Illuminate\Http\JsonResponse;

final class MyClassBulletinController extends Controller
{
    public function __construct(
        private ManageMyClassStudentsService $students,
        private BulletinCompositionService $bulletins,
    ) {}

    public function show(
        MyClassStudentRequest $request,
        int $classe,
        int $inscription,
    ): JsonResponse {
        $enrollment = $this->students->enrollment(
            $request->schoolClass(),
            $inscription,
        );

        return TeacherBulletinResource::make(
            $this->bulletins->compose($enrollment),
        )->response();
    }
}
