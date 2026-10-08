<?php

use App\Domains\Tenant\Contracts\IdempotencyServiceInterface;
use App\Domains\Tenant\Exceptions\IdempotencyConflictException;
use App\Models\Etablissement;
use App\Models\Tenant;
use App\Models\UtilisateurPlateforme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('aggregates generate a ULID public identifier on creation', function () {
    $tenant = Tenant::query()->create([
        'uuid' => Str::uuid(),
        'nom_espace' => 'École ULID',
        'slug' => 'ecole-ulid',
        'type_tenant' => 'ECOLE',
        'statut' => 'ACTIF',
    ]);

    $etablissement = Etablissement::query()->create([
        'tenant_id' => $tenant->id,
        'nom' => 'Complexe ULID',
        'code_ministere' => 'ULID1',
        'province' => 'Kinshasa',
        'ville' => 'Kinshasa',
        'actif' => true,
    ]);

    $platformUser = UtilisateurPlateforme::factory()->create();

    expect(Str::isUlid($etablissement->public_id))->toBeTrue()
        ->and(Str::isUlid($platformUser->public_id))->toBeTrue()
        ->and(Etablissement::findByPublicId($etablissement->public_id)?->is($etablissement))->toBeTrue()
        ->and(UtilisateurPlateforme::findByPublicId($platformUser->public_id)?->is($platformUser))->toBeTrue();
});

test('the idempotency registry replays the initial result for the same key and payload', function () {
    $service = app(IdempotencyServiceInterface::class);
    $payload = ['command' => 'publish', 'bulletin_id' => 41];
    $requestHash = $service->hashPayload($payload);

    $first = $service->execute(
        key: 'plat-bulletin-41',
        commandName: 'publish.bulletin',
        callback: fn (): UtilisateurPlateforme => UtilisateurPlateforme::factory()->create(),
        requestHash: $requestHash,
    );

    $second = $service->execute(
        key: 'plat-bulletin-41',
        commandName: 'publish.bulletin',
        callback: fn (): UtilisateurPlateforme => UtilisateurPlateforme::factory()->create(),
        requestHash: $requestHash,
    );

    expect($first->is($second))->toBeTrue()
        ->and(UtilisateurPlateforme::query()->count())->toBe(1);
});

test('the idempotency registry rejects a reused key with a different payload', function () {
    $service = app(IdempotencyServiceInterface::class);

    $service->execute(
        key: 'plat-bulletin-42',
        commandName: 'publish.bulletin',
        callback: fn (): UtilisateurPlateforme => UtilisateurPlateforme::factory()->create(),
        requestHash: $service->hashPayload(['valeur' => 1]),
    );

    expect(fn () => $service->execute(
        key: 'plat-bulletin-42',
        commandName: 'publish.bulletin',
        callback: fn (): UtilisateurPlateforme => UtilisateurPlateforme::factory()->create(),
        requestHash: $service->hashPayload(['valeur' => 2]),
    ))->toThrow(IdempotencyConflictException::class);
});

test('the idempotency registry rejects invalid keys', function (string $key) {
    $service = app(IdempotencyServiceInterface::class);

    expect(fn () => $service->execute(
        key: $key,
        commandName: 'publish.bulletin',
        callback: fn (): UtilisateurPlateforme => UtilisateurPlateforme::factory()->create(),
    ))->toThrow(IdempotencyConflictException::class);
})->with([
    'empty key' => [''],
    'oversized key' => [str_repeat('a', 192)],
]);

test('the idempotency registry hashes payloads canonically', function () {
    $service = app(IdempotencyServiceInterface::class);

    expect($service->hashPayload(['a' => 1, 'b' => 2]))
        ->toBe(hash('sha256', '{"a":1,"b":2}'));
});
