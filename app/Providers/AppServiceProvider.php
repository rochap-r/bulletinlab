<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domains\Auth\Contracts\AuthServiceInterface;
use App\Domains\Auth\Services\AuthService;
use App\Domains\Tenant\Contracts\TenantServiceInterface;
use App\Domains\Tenant\Services\TenantService;
use App\Domains\SchoolYear\Contracts\OpenSchoolYearServiceInterface;
use App\Domains\SchoolYear\Services\OpenSchoolYearService;
use App\Domains\Student\Contracts\ImportStudentsServiceInterface;
use App\Domains\Student\Services\ImportStudentsService;
use App\Domains\Evaluation\Contracts\NoteEntryServiceInterface;
use App\Domains\Evaluation\Services\NoteEntryService;
use App\Models\Note;
use App\Observers\NoteObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(TenantServiceInterface::class, TenantService::class);
        $this->app->bind(OpenSchoolYearServiceInterface::class, OpenSchoolYearService::class);
        $this->app->bind(ImportStudentsServiceInterface::class, ImportStudentsService::class);
        $this->app->bind(NoteEntryServiceInterface::class, NoteEntryService::class);
    }

    public function boot(): void
    {
        Note::observe(NoteObserver::class);
    }
}
