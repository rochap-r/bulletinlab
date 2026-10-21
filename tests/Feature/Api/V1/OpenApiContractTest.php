<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * @return array<string, mixed>
 */
function openApiV1Document(): array
{
    $contents = file_get_contents(base_path('openapi-v1.json'));

    if ($contents === false) {
        throw new RuntimeException('The OpenAPI V1 document could not be read.');
    }

    return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
}

/**
 * @param  array<string, mixed>  $document
 * @return Collection<int, array{method: string, path: string, route_name: string, operation: array<string, mixed>}>
 */
function openApiV1Operations(array $document): Collection
{
    $httpMethods = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];

    return collect($document['paths'])
        ->flatMap(
            fn (array $pathItem, string $path): Collection => collect($pathItem)
                ->filter(
                    fn (mixed $operation, string $method): bool => in_array(
                        $method,
                        $httpMethods,
                        true,
                    ) && is_array($operation),
                )
                ->map(
                    fn (array $operation, string $method): array => [
                        'method' => strtoupper($method),
                        'path' => $path,
                        'route_name' => $operation['x-laravel-route-name'],
                        'operation' => $operation,
                    ],
                )
                ->values(),
        )
        ->values();
}

test('the interim OpenAPI V1 document defines the stable foundation', function () {
    $document = openApiV1Document();

    expect($document)
        ->toHaveKey('openapi', '3.1.1')
        ->toHaveKey('jsonSchemaDialect')
        ->toHaveKey('x-contract-status', 'interim-current-schema')
        ->toHaveKey('x-retired-prototype-routes', [
            'POST /notes',
            'PUT /notes/{note}',
            'PATCH /notes/{note}',
            'POST /notes/import',
        ])
        ->and($document['servers'][0]['url'])->toBe('/api/v1')
        ->and($document['x-contexts']['tenant']['clientManagedTenantId'])->toBeFalse()
        ->and($document['x-contexts']['establishment']['xEstablishmentIdSupported'])->toBeFalse()
        ->and($document['components']['securitySchemes']['sanctumBearer'])->toMatchArray([
            'type' => 'http',
            'scheme' => 'bearer',
        ])
        ->and($document['components']['schemas']['ProblemDetails']['required'])
        ->toBe(['type', 'title', 'status', 'detail', 'instance'])
        ->and($document['components']['schemas']['ValidationProblemDetails']['allOf'])
        ->toHaveCount(2)
        ->and($document['components']['parameters']['PerPage']['schema'])
        ->toMatchArray([
            'minimum' => 1,
            'maximum' => 100,
            'default' => 50,
        ])
        ->and($document['components']['parameters']['Page']['schema'])
        ->toMatchArray([
            'minimum' => 1,
            'default' => 1,
        ])
        ->and(
            $document['components']['parameters']['EnrollmentId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['StudentId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['ClassId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['NoteId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['BulletinId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['ModelPeriodId']['x-public-id-status'],
        )->toBe('deferred')
        ->and(
            $document['components']['parameters']['BulletinDecisionStatus']['schema']['enum'],
        )->toBe([
            'EN_COURS',
            'PASSE',
            'DOUBLE',
            'ECHOUE',
            'REPECHAGE',
            'REORIENTE',
            'DIPLOME',
        ]);
    expect(
        $document['components']['parameters']['CourseNodeId']['x-course-model-status'],
    )->toBe('temporary-modele-node');
    expect(
        $document['components']['parameters']['CourseAssignmentId']['x-assignment-model-status'],
    )->toBe('temporary-attribution-cours');
    expect(
        $document['components']['parameters']['GradeEntrySlotId']['x-slot-model-status'],
    )->toBe('temporary-modele-maximum');
});

test('every documented OpenAPI operation matches a named runtime route', function () {
    $operations = openApiV1Operations(openApiV1Document());
    $runtimeRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(
            fn (LaravelRoute $route): bool => Str::startsWith(
                (string) $route->getName(),
                'api.v1.',
            ),
        )
        ->flatMap(
            fn (LaravelRoute $route): Collection => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): array => [
                    'method' => $method,
                    'path' => '/'.Str::after($route->uri(), 'api/v1/'),
                    'route_name' => (string) $route->getName(),
                ]),
        );
    expect($operations)->toHaveCount(40)
        ->and($operations->pluck('operation.operationId')->unique())->toHaveCount(40);

    foreach ($operations as $documentedOperation) {
        expect($runtimeRoutes->contains(
            fn (array $runtimeRoute): bool => $runtimeRoute['method'] === $documentedOperation['method']
                && $runtimeRoute['path'] === $documentedOperation['path']
                && $runtimeRoute['route_name'] === $documentedOperation['route_name'],
        ))->toBeTrue();

        expect($documentedOperation['operation'])
            ->toHaveKey('responses.default.$ref', '#/components/responses/Problem');
    }
});

test('the OpenAPI authentication contexts fail closed', function () {
    $document = openApiV1Document();

    expect($document['paths']['/auth/login']['post'])
        ->toHaveKey('security', [])
        ->toHaveKey('x-context', 'public')
        ->and($document['paths']['/auth/logout']['post']['security'])
        ->toBe([['sanctumBearer' => []]])
        ->and($document['paths']['/me']['get']['security'])
        ->toBe([['sanctumBearer' => []]])
        ->and($document['paths']['/me']['get']['x-context'])
        ->toBe('tenant')
        ->and($document['components']['requestBodies']['TenantLogin']['content'])
        ->toHaveKey('application/json')
        ->and($document['components']['responses']['ValidationFailed']['content'])
        ->toHaveKey('application/problem+json');
});

test('the OpenAPI platform authentication contexts fail closed', function () {
    $document = openApiV1Document();

    expect($document['paths']['/platform/auth/login']['post'])
        ->toHaveKey('security', [])
        ->toHaveKey('x-context', 'public')
        ->toHaveKey('x-laravel-route-name', 'api.v1.platform.auth.login')
        ->and($document['paths']['/platform/auth/logout']['post']['security'])
        ->toBe([['sanctumBearer' => []]])
        ->and($document['paths']['/platform/me']['get']['security'])
        ->toBe([['sanctumBearer' => []]])
        ->and($document['paths']['/platform/me']['get']['x-context'])
        ->toBe('platform')
        ->and($document['x-contexts']['platform']['source'])
        ->toBe('Sanctum token ability context:platform')
        ->and($document['components']['schemas']['PlatformUser']['required'])
        ->toContain('public_id', 'nom', 'email', 'roles', 'actif');
});

test('the interim OpenAPI contract describes the composed empty report card', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/inscriptions/{inscription}/bulletin']['get'];
    $example = $document['components']['responses']['EnrollmentReportCard']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.inscriptions.bulletin.show',
        )
        ->toHaveKey('x-context', 'establishment')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/EnrollmentId')
        ->and($operation['responses'])
        ->toHaveKeys(['200', '401', '403', '404', '409', '429', 'default'])
        ->and($document['components']['schemas']['ReportCardComposition']['required'])
        ->toContain('inscription', 'classe', 'modele', 'nodes', 'periodes', 'slots')
        ->and($document['components']['schemas']['ReportCardGrade']['required'])
        ->toContain('lock_version', 'statut_resultat', 'valeur')
        ->and($example['slots'][0]['note'])->toBeNull()
        ->and($example['nodes'][0]['libelle'])->toBe('Mathématiques');
});

test('the interim OpenAPI contract describes contextual report card reads', function () {
    $document = openApiV1Document();
    $show = $document['paths']['/bulletins/{bulletin}']['get'];
    $classIndex = $document['paths']['/classes/{classe}/bulletins']['get'];

    expect($document['paths'])->not->toHaveKey('/bulletins');

    expect($show)
        ->toHaveKey('x-laravel-route-name', 'api.v1.bulletins.show')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'view')
        ->and($show['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/BulletinId')
        ->and($show['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ]);

    expect($classIndex)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.bulletins.by-classe',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewClass')
        ->and($classIndex['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($classIndex['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($classIndex['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($classIndex['parameters'][3]['$ref'])
        ->toBe('#/components/parameters/BulletinDecisionStatus')
        ->and($classIndex['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['BulletinSummary']['required'],
        )->toContain(
            'inscription_id',
            'classe_id',
            'modele_bulletin_id',
            'decision_statut',
            'eleve',
        )
        ->and(
            $document['components']['schemas']['BulletinCollectionEnvelope']['required'],
        )->toBe(['data', 'links', 'meta']);
});

test('the interim OpenAPI contract describes the non official calculation preview', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/bulletins/{bulletin}/periodes/{periode}/apercu-calcul']['get'];
    $schema = $document['components']['schemas']['BulletinCalculationPreview'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.bulletins.periodes.apercu-calcul.show',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'previewCalculation')
        ->toHaveKey('x-contract-status', 'current-schema-non-official')
        ->toHaveKey('x-persisted', false)
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/BulletinId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toContain(
            'official',
            'persisted',
            'engine_version',
            'configuration_hash',
            'input_hash',
            'result_hash',
            'calculable',
            'publication_ready',
            'blockers',
        )
        ->and($schema['properties']['official']['const'])->toBeFalse()
        ->and($schema['properties']['persisted']['const'])->toBeFalse()
        ->and($schema['properties']['engine_version']['const'])->toBe('1.0.0')
        ->and(
            $document['components']['schemas']['Sha256Hash']['pattern'],
        )->toBe('^[a-f0-9]{64}$');
});

test('the interim OpenAPI contract describes the official report card publication', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/bulletins/{bulletin}/periodes/{periode}/publier']['post'];
    $schema = $document['components']['schemas']['BulletinPublication'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.bulletins.periodes.publier.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'publishCalculation')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-persisted', true)
        ->toHaveKey('x-idempotency-mode', 'unique-publication')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/BulletinId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toContain(
            'official',
            'persisted',
            'engine_version',
            'configuration_hash',
            'input_hash',
            'result_hash',
            'total_obtenu',
            'total_maxima',
            'pourcentage',
            'mention',
            'decision',
            'rang',
            'nombre_eleves_classe',
            'publication_id',
            'version',
            'publication_statut',
        )
        ->and($schema['properties']['official']['const'])->toBeTrue()
        ->and($schema['properties']['persisted']['const'])->toBeTrue()
        ->and($schema['properties']['engine_version']['const'])->toBe('1.0.0')
        ->and($schema['properties']['publication_statut']['enum'])
        ->toBe(['PUBLIEE', 'RECTIFIEE']);
});

test('the interim OpenAPI contract describes the report card rectification', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/bulletins/{bulletin}/periodes/{periode}/rectifications']['post'];
    $schema = $document['components']['schemas']['BulletinRectification'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.bulletins.periodes.rectifications.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'rectifyPublication')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-persisted', true)
        ->toHaveKey('x-idempotency-mode', 'none')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/BulletinId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['requestBody']['required'])->toBeTrue()
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toBe(['motif'])
        ->and($schema['properties']['motif']['minLength'])->toBe(1)
        ->and($schema['properties']['motif']['maxLength'])->toBe(1000);
});

test('the interim OpenAPI contract describes the class proclamation generation', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/periodes/{periode}/proclamations']['post'];
    $schema = $document['components']['schemas']['Proclamation'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.periodes.proclamations.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'generateProclamation')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toContain(
            'id',
            'type',
            'statut',
            'classe_id',
            'periode_id',
            'date_generation',
            'generee_par',
            'metadata',
        )
        ->and($schema['properties']['type']['enum'])
        ->toBe(['PERIODE', 'TRIMESTRE', 'SEMESTRE', 'ANNUELLE', 'FINALE'])
        ->and($schema['properties']['statut']['enum'])
        ->toBe(['BROUILLON', 'GENEREE', 'VALIDEE', 'ANNULEE']);
});

test('the interim OpenAPI contract describes contextual note reads and history', function () {
    $document = openApiV1Document();
    $index = $document['paths']['/notes']['get'];
    $show = $document['paths']['/notes/{note}']['get'];
    $classIndex = $document['paths']['/classes/{classe}/notes']['get'];
    $history = $document['paths']['/notes/{note}/historique']['get'];

    expect($index)
        ->toHaveKey('x-laravel-route-name', 'api.v1.notes.index')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewAny')
        ->and($index['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($index['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($index['responses'])
        ->toHaveKeys(['200', '401', '403', '422', '429', 'default']);

    expect($show)
        ->toHaveKey('x-laravel-route-name', 'api.v1.notes.show')
        ->toHaveKey('x-authorization-ability', 'view')
        ->and($show['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/NoteId')
        ->and($show['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '422',
            '429',
            'default',
        ]);

    expect($classIndex)
        ->toHaveKey('x-laravel-route-name', 'api.v1.notes.by-classe')
        ->toHaveKey('x-authorization-ability', 'viewClass')
        ->and($classIndex['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($classIndex['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($classIndex['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/Page');

    expect($history)
        ->toHaveKey('x-laravel-route-name', 'api.v1.notes.history')
        ->toHaveKey('x-authorization-ability', 'viewHistory')
        ->and($history['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/NoteId')
        ->and($history['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($history['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($history['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['NoteCollectionEnvelope']['required'],
        )->toBe(['data', 'links', 'meta'])
        ->and($document['components']['schemas']['Note']['required'])
        ->toContain(
            'lock_version',
            'statut_resultat',
            'eleve',
            'cours',
            'periode',
        )
        ->and(
            $document['components']['schemas']['NoteHistoryEntry']['required'],
        )->toContain(
            'ancien_statut_resultat',
            'nouveau_statut_resultat',
            'modifie_par',
            'motif',
        )
        ->and(
            $document['components']['responses']['NoteHistory']['content']['application/json']['example']['data'][0]['nouvelle_valeur'],
        )->toBe('16.00');
});

test('the interim OpenAPI contract describes controlled class archival', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/archivage']['post'];
    $example = $document['components']['responses']['ArchivedClass']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.archivage.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/ClassArchive')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['ClassArchiveCommand']['required'])
        ->toBe(['motif'])
        ->and($document['components']['schemas']['SchoolClass']['required'])
        ->toContain('annee_scolaire_id', 'system_template_id', 'active')
        ->and($example['active'])->toBeFalse();
});

test('the interim OpenAPI contract describes controlled course assignment', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/cours/{node}/attribution']['put'];
    $example = $document['components']['responses']['AssignedCourseTeacher']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.cours.attribution.update',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->toHaveKey('x-course-model-status', 'temporary-modele-node')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/CourseNodeId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/CourseTeacherAssignment')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['CourseTeacherAssignmentCommand']['required'],
        )->toBe(['enseignant_id'])
        ->and($document['components']['schemas']['CourseAssignment']['required'])
        ->toContain('classe_id', 'cours', 'enseignant', 'actif')
        ->and($example['cours']['id'])->toBe(81)
        ->and($example['enseignant']['id'])->toBe(17);
});

test('the interim OpenAPI contract describes the controlled class course list', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/cours']['get'];
    $example = $document['components']['responses']['ClassCourses']['content']['application/json']['example'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.cours.index',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-course-model-status', 'temporary-modele-node')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($operation['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['ClassCourse']['required'])
        ->toContain(
            'id',
            'libelle',
            'attribuable',
            'attribution_active',
        )
        ->and($example['data'][0]['id'])->toBe(81)
        ->and($example['data'][0]['attribution_active']['enseignant']['id'])
        ->toBe(17)
        ->and($example['meta']['total'])->toBe(1);
});

test('the interim OpenAPI contract describes the current teacher assignments', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/me/attributions']['get'];
    $example = $document['components']['responses']['MyCourseAssignments']['content']['application/json']['example'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.me.attributions.index',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewAny')
        ->toHaveKey('x-course-model-status', 'temporary-modele-node')
        ->toHaveKey('x-temporal-scope', 'current-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['MyCourseAssignment']['required'],
        )->toBe([
            'id',
            'classe',
            'annee_scolaire',
            'cours',
            'date_debut',
            'date_fin',
            'actif',
        ])
        ->and($example['data'][0]['id'])->toBe(51)
        ->and($example['data'][0]['classe']['id'])->toBe(8)
        ->and($example['data'][0]['cours']['id'])->toBe(81)
        ->and($example['meta']['total'])->toBe(1);
});

test('the interim OpenAPI contract describes the current assignment entry slots', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/me/attributions/{attribution}/creneaux']['get'];
    $example = $document['components']['responses']['MyCourseAssignmentSlots']['content']['application/json']['example'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.me.attributions.creneaux.index',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewSlots')
        ->toHaveKey('x-course-model-status', 'temporary-modele-node')
        ->toHaveKey(
            'x-assignment-model-status',
            'temporary-attribution-cours',
        )
        ->toHaveKey('x-slot-model-status', 'temporary-modele-maximum')
        ->toHaveKey('x-temporal-scope', 'current-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/CourseAssignmentId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($operation['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentSlot']['required'],
        )->toBe([
            'id',
            'attribution_id',
            'periode',
            'maximum',
            'coefficient',
            'verrouille',
            'saisie_autorisee',
        ])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentSlot']['x-slot-model-status'],
        )->toBe('temporary-modele-maximum')
        ->and($example['data'][0]['id'])->toBe(91)
        ->and($example['data'][0]['attribution_id'])->toBe(51)
        ->and($example['data'][0]['maximum'])->toBe('20.00')
        ->and($example['data'][0]['saisie_autorisee'])->toBeTrue()
        ->and($example['meta']['total'])->toBe(1);
});

test('the interim OpenAPI contract describes the sparse current grade sheet', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/me/attributions/{attribution}/creneaux/{creneau}/feuille-notes']['get'];
    $example = $document['components']['responses']['MyCourseAssignmentGradeSheet']['content']['application/json']['example'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.me.attributions.creneaux.feuille-notes.show',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewGradeSheet')
        ->toHaveKey(
            'x-assignment-model-status',
            'temporary-attribution-cours',
        )
        ->toHaveKey('x-slot-model-status', 'temporary-modele-maximum')
        ->toHaveKey('x-temporal-scope', 'current-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/CourseAssignmentId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/GradeEntrySlotId')
        ->and($operation['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($operation['parameters'][3]['$ref'])
        ->toBe('#/components/parameters/Page')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentGradeSheetEnvelope']['required'],
        )->toBe(['data', 'creneau', 'links', 'meta'])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentGradeSheetRow']['required'],
        )->toBe(['inscription', 'note'])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentGradeSheetRow']['properties']['note']['oneOf'][1]['required'],
        )->toContain('statut_resultat', 'valeur')
        ->and($example['data'][0]['note']['lock_version'])->toBe(1)
        ->and($example['data'][0]['note']['statut_resultat'])
        ->toBe('SAISIE')
        ->and($example['data'][0]['note']['valeur'])->toBe('14.50')
        ->and($example['data'][1]['note'])->toBeNull()
        ->and($example['creneau']['id'])->toBe(91)
        ->and($example['meta']['total'])->toBe(2);
});

test('the interim OpenAPI contract describes idempotent teacher note upsert', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/me/attributions/{attribution}/creneaux/{creneau}/inscriptions/{inscription}/note']['put'];
    $example = $document['components']['responses']['UpsertedMyCourseAssignmentNote']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.me.attributions.creneaux.inscriptions.note.update',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'upsertNote')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->toHaveKey('x-temporal-scope', 'current-only')
        ->toHaveKey(
            'x-assignment-model-status',
            'temporary-attribution-cours',
        )
        ->toHaveKey('x-slot-model-status', 'temporary-modele-maximum')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/CourseAssignmentId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/GradeEntrySlotId')
        ->and($operation['parameters'][2]['$ref'])
        ->toBe('#/components/parameters/EnrollmentId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/MyCourseAssignmentNoteUpsert')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '423',
            '429',
            'default',
        ])
        ->and(
            array_key_exists(
                'required',
                $document['components']['schemas']['MyCourseAssignmentNoteUpsertCommand'],
            ),
        )->toBeFalse()
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteUpsertCommand']['oneOf'],
        )->toHaveCount(2)
        ->and($document['components']['schemas']['NoteResultStatus']['enum'])
        ->toBe(['SAISIE', 'ABSENTE', 'DISPENSEE', 'ANNULEE'])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteUpsertCommand']['properties'],
        )->toHaveKey('lock_version')
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteUpsertCommand']['properties']['lock_version']['maximum'],
        )->toBe(4294967294)
        ->and(
            $document['components']['schemas']['ProblemDetails']['properties']['code']['description'],
        )->toContain('NOTE_VERSION_CONFLICT')
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNote']['required'],
        )->toBe(['inscription', 'creneau_id', 'note'])
        ->and($example['inscription']['id'])->toBe(31)
        ->and($example['creneau_id'])->toBe(91)
        ->and($example['note']['lock_version'])->toBe(2)
        ->and($example['note']['statut_resultat'])->toBe('SAISIE')
        ->and($example['note']['valeur'])->toBe('16.00');
});

test('the interim OpenAPI contract describes atomic teacher note import', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/me/attributions/{attribution}/creneaux/{creneau}/imports-notes']['post'];
    $requestBody = $document['components']['requestBodies']['MyCourseAssignmentNoteImport'];
    $example = $document['components']['responses']['ImportedMyCourseAssignmentNotes']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.me.attributions.creneaux.imports-notes.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'importNotes')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->toHaveKey('x-temporal-scope', 'current-only')
        ->toHaveKey(
            'x-assignment-model-status',
            'temporary-attribution-cours',
        )
        ->toHaveKey('x-slot-model-status', 'temporary-modele-maximum')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/CourseAssignmentId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/GradeEntrySlotId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/MyCourseAssignmentNoteImport')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '423',
            '429',
            'default',
        ])
        ->and($requestBody['content'])->toHaveKey('multipart/form-data')
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteImportCommand']['required'],
        )->toBe(['file'])
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteImportCommand']['properties']['file']['description'],
        )->toContain(
            'matricule,note,statut_resultat',
            'matricule,note,lock_version',
        )
        ->and(
            $document['components']['schemas']['MyCourseAssignmentNoteImportResult']['required'],
        )->toBe(['total', 'created', 'corrected', 'unchanged'])
        ->and($example['total'])->toBe(
            $example['created'] + $example['corrected'] + $example['unchanged'],
        );
});

test('the interim OpenAPI contract describes controlled class titular assignment', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/titulariat']['put'];
    $example = $document['components']['responses']['AssignedClassTitular']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.titulariat.update',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->toHaveKey('x-titular-model-status', 'transitional-dual-write')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/ClassTitularAssignment')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and(
            $document['components']['schemas']['ClassTitularAssignmentCommand']['required'],
        )->toBe(['titulaire_id'])
        ->and($document['components']['schemas']['ClassTitularAssignment']['required'])
        ->toContain('classe_id', 'titulaire', 'date_debut', 'actif')
        ->and($example['titulaire']['id'])->toBe(19)
        ->and($example['actif'])->toBeTrue();
});

test('the interim OpenAPI contract describes controlled student archival', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/eleves/{eleve}/archivage']['post'];
    $example = $document['components']['responses']['ArchivedStudent']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.eleves.archivage.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/StudentId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/StudentArchive')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['StudentArchiveCommand']['required'])
        ->toBe(['motif'])
        ->and($example['actif'])->toBeFalse();
});

test('the interim OpenAPI contract describes controlled student reactivation', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/eleves/{eleve}/reactivation']['post'];
    $example = $document['components']['responses']['ReactivatedStudent']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.eleves.reactivation.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/StudentId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/StudentReactivation')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['StudentReactivationCommand']['required'])
        ->toBe(['motif'])
        ->and($example['actif'])->toBeTrue();
});

test('the interim OpenAPI contract describes controlled enrollment exit', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/inscriptions/{inscription}/sorties']['post'];
    $example = $document['components']['responses']['ExitedEnrollment']['content']['application/json']['example']['data'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.inscriptions.sorties.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/EnrollmentId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/EnrollmentExit')
        ->and($operation['responses'])
        ->toHaveKeys([
            '200',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['EnrollmentExitCommand']['required'])
        ->toBe(['statut', 'date_sortie', 'motif'])
        ->and($example['statut'])->toBe('ABANDON')
        ->and($example['bulletin']['id'])->toBe(41);
});

test('the interim OpenAPI contract describes membership invitations and onboarding', function () {
    $document = openApiV1Document();
    $index = $document['paths']['/invitations']['get'];
    $store = $document['paths']['/invitations']['post'];
    $revoke = $document['paths']['/invitations/{invitation}']['delete'];
    $accept = $document['paths']['/public/invitations/acceptation']['post'];

    expect($index)
        ->toHaveKey('x-laravel-route-name', 'api.v1.invitations.index')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewAny')
        ->and($index['security'])->toBe([['sanctumBearer' => []]])
        ->and($index['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/PerPage')
        ->and($index['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/Page');

    expect($store)
        ->toHaveKey('x-laravel-route-name', 'api.v1.invitations.store')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'create')
        ->and($store['security'])->toBe([['sanctumBearer' => []]])
        ->and($store['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/InvitationCreate')
        ->and($store['responses'])
        ->toHaveKeys([
            '201',
            '401',
            '403',
            '404',
            '409',
            '422',
            '429',
            'default',
        ])
        ->and($document['components']['schemas']['InvitationCreateCommand']['additionalProperties'])
        ->toBeFalse()
        ->and($document['components']['schemas']['InvitationCreateCommand']['required'])
        ->toBe(['email', 'role_id']);

    expect($revoke)
        ->toHaveKey('x-laravel-route-name', 'api.v1.invitations.revoke')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'revoke')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($revoke['security'])->toBe([['sanctumBearer' => []]])
        ->and($revoke['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/InvitationId')
        ->and(
            $document['components']['parameters']['InvitationId']['x-public-id-status'],
        )->toBe('deferred');

    expect($accept)
        ->toHaveKey('x-laravel-route-name', 'api.v1.public.invitations.acceptation.store')
        ->toHaveKey('x-context', 'public')
        ->toHaveKey('security', [])
        ->and($accept['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/InvitationAccept')
        ->and($accept['responses'])
        ->toHaveKeys(['201', '401', '404', '409', '422', '429', 'default'])
        ->and($document['components']['schemas']['InvitationAcceptCommand']['additionalProperties'])
        ->toBeFalse()
        ->and($document['components']['schemas']['InvitationAcceptCommand']['required'])
        ->toBe(['token', 'nom', 'telephone', 'mot_de_passe'])
        ->and($document['components']['schemas']['Invitation']['required'])
        ->toContain('id', 'email', 'role', 'statut', 'expire_le')
        ->and($document['components']['schemas']['Invitation']['properties']['statut']['enum'])
        ->toBe(['EN_ATTENTE', 'ACCEPTEE', 'REVOQUEE', 'EXPIREE'])
        ->and($document['components']['schemas']['InvitationCreated']['allOf'])
        ->toHaveCount(2)
        ->and($document['components']['schemas']['InvitationCreated']['allOf'][1]['required'])
        ->toBe(['token'])
        ->and($document['components']['schemas']['InvitationCreated']['allOf'][1]['properties']['token']['writeOnly'])
        ->toBeTrue()
        ->and($document['components']['schemas']['OnboardedUser']['required'])
        ->toContain('email', 'telephone', 'actif', 'roles');
});

test('the interim OpenAPI contract describes the class deliberation', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/periodes/{periode}/deliberations']['post'];
    $command = $document['components']['schemas']['ClassDeliberationCommand'];
    $result = $document['components']['schemas']['ClassDeliberationResult'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.periodes.deliberations.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'deliberateClass')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/ClassDeliberation')
        ->and($command['additionalProperties'])->toBeFalse()
        ->and($command['required'])->toBe(['lieu_deliberation', 'date_deliberation'])
        ->and($command['properties']['lieu_deliberation']['minLength'])->toBe(1)
        ->and($command['properties']['decisions']['items']['required'])
        ->toBe(['bulletin_id', 'decision'])
        ->and($command['properties']['decisions']['items']['properties']['decision']['enum'])
        ->toBe(['PASSE', 'DOUBLE', 'ECHOUE', 'REPECHAGE', 'REORIENTE', 'DIPLOME'])
        ->and($result['required'])
        ->toContain('classe_id', 'periode_id', 'validated_count', 'skipped_count', 'details')
        ->and($result['properties']['details']['items']['properties']['status']['enum'])
        ->toBe(['validated', 'skipped']);
});

test('the interim OpenAPI contract describes the class publication', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/periodes/{periode}/publications']['post'];
    $result = $document['components']['schemas']['ClassPublicationResult'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.periodes.publications.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'publishClass')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation)->not->toHaveKey('requestBody')
        ->and($result['required'])
        ->toBe(['classe_id', 'periode_id', 'published_count', 'already_published_count', 'failed_count', 'results'])
        ->and($result['properties']['results']['items']['required'])
        ->toBe(['bulletin_id', 'status'])
        ->and($result['properties']['results']['items']['properties']['status']['enum'])
        ->toBe(['published', 'already_published', 'failed']);
});

test('the interim OpenAPI contract describes the class promotion generation', function () {
    $document = openApiV1Document();
    $operation = $document['paths']['/classes/{classe}/periodes/{periode}/promotions']['post'];
    $promotion = $document['components']['schemas']['PromotionAnnuelle'];
    $command = $document['components']['schemas']['ClassPromotionCommand'];

    expect($operation)
        ->toHaveKey(
            'x-laravel-route-name',
            'api.v1.classes.periodes.promotions.store',
        )
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'generatePromotion')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($operation['security'])->toBe([['sanctumBearer' => []]])
        ->and($operation['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/ClassId')
        ->and($operation['parameters'][1]['$ref'])
        ->toBe('#/components/parameters/ModelPeriodId')
        ->and($operation['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/ClassPromotion')
        ->and($command['additionalProperties'])->toBeFalse()
        ->and($command['properties']['annee_cible_id']['type'])
        ->toBe(['integer', 'null'])
        ->and($promotion['additionalProperties'])->toBeFalse()
        ->and($promotion['required'])
        ->toContain('id', 'annee_source_id', 'annee_cible_id', 'publication_id', 'statut', 'eleves_count')
        ->and($promotion['properties']['statut']['enum'])
        ->toBe(['BROUILLON', 'GENEREE', 'VALIDEE', 'ANNULEE']);
});

test('the interim OpenAPI contract describes the global school year reads and annual openings', function () {
    $document = openApiV1Document();
    $courante = $document['paths']['/annees-scolaires/courante']['get'];
    $store = $document['paths']['/ouvertures-annuelles']['post'];
    $cloture = $document['paths']['/ouvertures-annuelles/{ouverture_annuelle}/cloture']['post'];

    expect($courante)
        ->toHaveKey('x-laravel-route-name', 'api.v1.annees-scolaires.courante.show')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'viewAny')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->and($courante['security'])->toBe([['sanctumBearer' => []]])
        ->and($courante['responses'])
        ->toHaveKeys(['200', '401', '403', '404', '409', '429', 'default']);

    expect($store)
        ->toHaveKey('x-laravel-route-name', 'api.v1.ouvertures-annuelles.store')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'manageAcademicStructure')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($store['security'])->toBe([['sanctumBearer' => []]])
        ->and($store['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/OuvertureAnnuelleOuverture')
        ->and($store['responses'])
        ->toHaveKeys(['201', '401', '403', '404', '409', '422', '429', 'default']);

    expect($cloture)
        ->toHaveKey('x-laravel-route-name', 'api.v1.ouvertures-annuelles.cloture.store')
        ->toHaveKey('x-context', 'establishment')
        ->toHaveKey('x-authorization-ability', 'manageAcademicStructure')
        ->toHaveKey('x-contract-status', 'current-schema-official')
        ->toHaveKey('x-idempotency-mode', 'state-replay-only')
        ->and($cloture['security'])->toBe([['sanctumBearer' => []]])
        ->and($cloture['parameters'][0]['$ref'])
        ->toBe('#/components/parameters/OuvertureAnnuelleId')
        ->and($cloture['requestBody']['$ref'])
        ->toBe('#/components/requestBodies/OuvertureAnnuelleCloture')
        ->and($cloture['responses'])
        ->toHaveKeys(['200', '401', '403', '404', '409', '422', '429', 'default']);
});
