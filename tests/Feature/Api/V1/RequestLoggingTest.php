<?php

use App\Domains\Auth\Exceptions\AuthenticationException;
use App\Http\Responses\ProblemDetailsResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

afterEach(fn (): mixed => Context::flush());

test('api responses carry the request id', function (): void {
    $response = $this->getJson('/up');

    $response->assertOk();
    expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
});

test('server errors expose the request trace id', function (): void {
    Context::add(['request_id' => '9f9b8a7e-1234-4bcd-9a8b-1234567890ab']);

    $response = ProblemDetailsResponse::fromException(
        new RuntimeException('boom'),
        Request::create('/api/v1/me'),
    );

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true)['trace_id'])->toBe('9f9b8a7e-1234-4bcd-9a8b-1234567890ab');
});

test('client errors expose no trace id', function (): void {
    $response = ProblemDetailsResponse::fromException(
        new AuthenticationException('Invalid credentials'),
        Request::create('/api/v1/auth/login'),
    );

    expect($response->getStatusCode())->toBe(401)
        ->and($response->getData(true))->not->toHaveKey('trace_id');
});
