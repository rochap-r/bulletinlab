<?php

use App\Domains\Responsable\Services\ResponsableMonitoringService;
use App\Models\RolePlateforme;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::clear();
});

function responsableMonitoringPlatformToken(): string
{
    RolePlateforme::query()->updateOrCreate(
        ['code' => 'ADMIN_PLATEFORME'],
        ['nom' => 'Administrateur plateforme'],
    );

    $user = UtilisateurPlateforme::factory()->create(['actif' => true]);
    $user->roles()->syncWithoutDetaching(['ADMIN_PLATEFORME']);

    return $user->createToken('responsable-monitoring', ['context:platform'])->plainTextToken;
}

test('les metriques parent agregees sont lisibles uniquement par la plateforme', function (): void {
    $monitoring = app(ResponsableMonitoringService::class);
    $monitoring->recordAuthentication(false);
    $monitoring->recordRead('children', 12.4);
    $monitoring->recordRead('evaluations', 7.6);
    $monitoring->recordRead('evaluations', 3.2, false);

    $this->getJson('/api/v1/platform/responsables/monitoring')->assertUnauthorized();

    $this->withToken(responsableMonitoringPlatformToken())
        ->getJson('/api/v1/platform/responsables/monitoring')
        ->assertOk()
        ->assertJsonPath('data.0.metrics.auth_failure', 1)
        ->assertJsonPath('data.0.metrics.children_read', 1)
        ->assertJsonPath('data.0.metrics.evaluations_read', 1)
        ->assertJsonPath('data.0.metrics.read_failure', 1)
        ->assertJsonPath('data.0.metrics.read_duration_ms_total', 20)
        ->assertJsonPath('data.0.average_read_duration_ms', 10);
});
