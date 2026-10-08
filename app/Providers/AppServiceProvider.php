<?php

namespace App\Providers;

use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Contracts\PlatformAuthServiceInterface;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Auth\Services\PlatformAuthService;
use App\Domains\Bulletin\Contracts\BulletinCalculationServiceInterface;
use App\Domains\Bulletin\Contracts\PublishBulletinServiceInterface;
use App\Domains\Bulletin\Contracts\RankBulletinsServiceInterface;
use App\Domains\Bulletin\Contracts\ResolveMentionDecisionServiceInterface;
use App\Domains\Bulletin\Services\DeterministicBulletinCalculationService;
use App\Domains\Bulletin\Services\PublishBulletinCalculationService;
use App\Domains\Bulletin\Services\RankBulletinsService;
use App\Domains\Bulletin\Services\ResolveMentionDecisionService;
use App\Domains\Membership\Contracts\InvitationServiceInterface;
use App\Domains\Membership\Services\InvitationService;
use App\Domains\Student\Contracts\ImportStudentsServiceInterface;
use App\Domains\Student\Services\ImportStudentsService;
use App\Domains\Teacher\Contracts\AssignCourseTeacherServiceInterface;
use App\Domains\Teacher\Services\AssignCourseTeacherService;
use App\Domains\Tenant\Contexts\EstablishmentContext;
use App\Domains\Tenant\Contexts\PlatformContext;
use App\Domains\Tenant\Contexts\TenantContext;
use App\Domains\Tenant\Contracts\IdempotencyServiceInterface;
use App\Domains\Tenant\Contracts\TenantRegistrationServiceInterface;
use App\Domains\Tenant\Contracts\TenantServiceInterface;
use App\Domains\Tenant\Contracts\TenantValidationServiceInterface;
use App\Domains\Tenant\Services\IdempotencyService;
use App\Domains\Tenant\Services\TenantRegistrationService;
use App\Domains\Tenant\Services\TenantService;
use App\Domains\Tenant\Services\TenantValidationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(EstablishmentContext::class);
        $this->app->scoped(PlatformContext::class);
        $this->app->scoped(TenantContext::class);

        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(PlatformAuthServiceInterface::class, PlatformAuthService::class);
        $this->app->bind(
            BulletinCalculationServiceInterface::class,
            DeterministicBulletinCalculationService::class,
        );
        $this->app->bind(
            ResolveMentionDecisionServiceInterface::class,
            ResolveMentionDecisionService::class,
        );
        $this->app->bind(
            RankBulletinsServiceInterface::class,
            RankBulletinsService::class,
        );
        $this->app->bind(
            PublishBulletinServiceInterface::class,
            PublishBulletinCalculationService::class,
        );
        $this->app->bind(TenantServiceInterface::class, TenantService::class);
        $this->app->bind(
            TenantRegistrationServiceInterface::class,
            TenantRegistrationService::class,
        );
        $this->app->bind(
            TenantValidationServiceInterface::class,
            TenantValidationService::class,
        );
        $this->app->bind(IdempotencyServiceInterface::class, IdempotencyService::class);
        $this->app->bind(ImportStudentsServiceInterface::class, ImportStudentsService::class);
        $this->app->bind(
            AssignCourseTeacherServiceInterface::class,
            AssignCourseTeacherService::class,
        );
        $this->app->bind(InvitationServiceInterface::class, InvitationService::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('auth.login', function (Request $request): array {
            $credentialKey = implode('|', [
                Str::lower(trim((string) $request->input('tenant_slug'))),
                Str::lower(trim((string) $request->input('identifiant'))),
                $request->ip(),
            ]);

            return [
                Limit::perMinute(30)->by('ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(5)->by('credential:'.hash('sha256', $credentialKey)),
            ];
        });

        RateLimiter::for('platform.auth.login', function (Request $request): array {
            $credentialKey = implode('|', [
                Str::lower(trim((string) $request->input('email'))),
                $request->ip(),
            ]);

            return [
                Limit::perMinute(30)->by('ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(5)->by('credential:'.hash('sha256', $credentialKey)),
            ];
        });

        RateLimiter::for('api', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();
            $key = implode('|', [
                'user:'.($userId ?? 'guest'),
                'ip:'.hash('sha256', (string) $request->ip()),
            ]);

            return Limit::perMinute($request->isMethodSafe() ? 120 : 60)
                ->by($key);
        });

        RateLimiter::for('public.invitation.acceptation', function (Request $request): array {
            $tokenKey = hash('sha256', Str::lower(trim((string) $request->input('token'))));

            return [
                Limit::perMinute(10)->by('ip:'.hash('sha256', (string) $request->ip())),
                Limit::perMinute(5)->by('invitation-token:'.$tokenKey),
            ];
        });

        RateLimiter::for('public.tenant.registration', function (Request $request): array {
            $emailKey = hash('sha256', Str::lower(trim((string) $request->input('admin_email'))));

            return [
                Limit::perMinute(10)->by('ip:'.hash('sha256', (string) $request->ip())),
                Limit::perHour(20)->by('ip-hour:'.hash('sha256', (string) $request->ip())),
                Limit::perHour(3)->by('email:'.$emailKey),
            ];
        });
    }
}
