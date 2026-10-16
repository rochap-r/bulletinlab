<?php

use App\Support\PhoneNumberNormalizer;

test('it normalizes common E.164 formatting', function (string $input, string $expected): void {
    expect(PhoneNumberNormalizer::normalize($input))->toBe($expected);
})->with([
    'spaces' => ['+243 811 000 001', '+243811000001'],
    'international prefix' => ['00243 811 000 001', '+243811000001'],
    'punctuation' => ['+243 (811)-000-001', '+243811000001'],
    'country code without plus' => ['243992522582', '+243992522582'],
    'national trunk zero' => ['0992522582', '+243992522582'],
    'trunk zero with spaces' => ['099 252 2582', '+243992522582'],
    'plain digits with country code' => ['243000000002', '+243000000002'],
]);

test('it rejects a phone number outside E.164', function (string $input): void {
    PhoneNumberNormalizer::normalize($input);
})->with([
    'too short local' => '0811',
    'letters' => '+243ABC000001',
    'too long' => '+2438110000019999',
])->throws(InvalidArgumentException::class);
