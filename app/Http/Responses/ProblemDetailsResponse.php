<?php

namespace App\Http\Responses;

use App\Domains\Auth\Exceptions\AuthenticationException as DomainAuthenticationException;
use App\Domains\Auth\Exceptions\PlatformAuthenticationException;
use App\Domains\Bulletin\Exceptions\BulletinException;
use App\Domains\Evaluation\Exceptions\EvaluationException;
use App\Domains\Evaluation\Exceptions\NoteVersionConflictException;
use App\Domains\Evaluation\Exceptions\PeriodLockedException;
use App\Domains\Membership\Exceptions\InvitationConflictException;
use App\Domains\Membership\Exceptions\InvitationNotFoundException;
use App\Domains\Referentiel\Exceptions\ReferentielConflictException;
use App\Domains\SchoolYear\Exceptions\SchoolYearException;
use App\Domains\Student\Exceptions\EnrollmentConflictException;
use App\Domains\Student\Exceptions\StudentArchiveConflictException;
use App\Domains\Teacher\Exceptions\ClassCourseListingConflictException;
use App\Domains\Teacher\Exceptions\ClassTitularAssignmentConflictException;
use App\Domains\Teacher\Exceptions\CourseAssignmentConflictException;
use App\Domains\Teacher\Exceptions\TeacherAssignmentListingConflictException;
use App\Domains\Teacher\Exceptions\TeacherAssignmentSlotListingConflictException;
use App\Domains\Teacher\Exceptions\TeacherGradeSheetConflictException;
use App\Domains\Teacher\Exceptions\TeacherNoteEntryConflictException;
use App\Domains\Template\Exceptions\ConfigurationBulletinConflictException;
use App\Domains\Tenant\Exceptions\EstablishmentNotFoundException;
use App\Domains\Tenant\Exceptions\PlatformContextNotFoundException;
use App\Domains\Tenant\Exceptions\TenantNotFoundException;
use App\Domains\Tenant\Exceptions\TenantValidationConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ProblemDetailsResponse
{
    public static function fromException(Throwable $exception, Request $request): JsonResponse
    {
        $status = self::statusFor($exception);
        $title = Response::$statusTexts[$status] ?? 'Error';
        $detail = $status >= 500
            ? 'An internal server error occurred.'
            : ($exception->getMessage() ?: $title);

        $problem = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
        ];

        if ($exception instanceof ValidationException) {
            $problem['errors'] = $exception->errors();
        }

        if ($exception instanceof NoteVersionConflictException) {
            $problem['code'] = 'NOTE_VERSION_CONFLICT';
        }

        if ($status >= 500) {
            $traceId = Context::get('request_id');

            if (is_string($traceId) && $traceId !== '') {
                $problem['trace_id'] = $traceId;
            }
        }

        $headers = $exception instanceof HttpExceptionInterface
            ? $exception->getHeaders()
            : [];

        $headers['Content-Type'] = 'application/problem+json';

        return response()->json($problem, $status, $headers, JSON_UNESCAPED_UNICODE);
    }

    private static function statusFor(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof AuthenticationException,
            $exception instanceof DomainAuthenticationException,
            $exception instanceof PlatformAuthenticationException => Response::HTTP_UNAUTHORIZED,
            $exception instanceof AuthorizationException => Response::HTTP_FORBIDDEN,
            $exception instanceof PeriodLockedException => Response::HTTP_LOCKED,
            $exception instanceof NoteVersionConflictException => Response::HTTP_CONFLICT,
            $exception instanceof EvaluationException => Response::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof BulletinException => Response::HTTP_CONFLICT,
            $exception instanceof EnrollmentConflictException => Response::HTTP_CONFLICT,
            $exception instanceof StudentArchiveConflictException => Response::HTTP_CONFLICT,
            $exception instanceof ReferentielConflictException => Response::HTTP_CONFLICT,
            $exception instanceof ConfigurationBulletinConflictException => Response::HTTP_CONFLICT,
            $exception instanceof CourseAssignmentConflictException => Response::HTTP_CONFLICT,
            $exception instanceof ClassTitularAssignmentConflictException => Response::HTTP_CONFLICT,
            $exception instanceof ClassCourseListingConflictException => Response::HTTP_CONFLICT,
            $exception instanceof TeacherAssignmentListingConflictException => Response::HTTP_CONFLICT,
            $exception instanceof TeacherAssignmentSlotListingConflictException => Response::HTTP_CONFLICT,
            $exception instanceof TeacherGradeSheetConflictException => Response::HTTP_CONFLICT,
            $exception instanceof TeacherNoteEntryConflictException => Response::HTTP_CONFLICT,
            $exception instanceof SchoolYearException => Response::HTTP_CONFLICT,
            $exception instanceof InvitationConflictException => Response::HTTP_CONFLICT,
            $exception instanceof TenantValidationConflictException => Response::HTTP_CONFLICT,
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException,
            $exception instanceof EstablishmentNotFoundException,
            $exception instanceof PlatformContextNotFoundException,
            $exception instanceof InvitationNotFoundException,
            $exception instanceof TenantNotFoundException => Response::HTTP_NOT_FOUND,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }
}
