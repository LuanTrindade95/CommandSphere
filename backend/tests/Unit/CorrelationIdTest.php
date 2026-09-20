<?php

use App\Support\CorrelationId;
use Illuminate\Http\Request;

it('generates a non-empty, format-valid correlation id', function (): void {
    $id = CorrelationId::generate();

    expect($id)->not->toBeEmpty()
        ->and(CorrelationId::isValid($id))->toBeTrue();
});

it('accepts safe, bounded correlation id formats', function (): void {
    expect(CorrelationId::isValid('abc-123_ok.value:1'))->toBeTrue()
        ->and(CorrelationId::isValid(str_repeat('a', 128)))->toBeTrue();
});

it('rejects null, empty, oversized, and unsafe correlation id values', function (): void {
    expect(CorrelationId::isValid(null))->toBeFalse()
        ->and(CorrelationId::isValid(''))->toBeFalse()
        ->and(CorrelationId::isValid(str_repeat('a', 129)))->toBeFalse()
        ->and(CorrelationId::isValid("abc\r\nX-Injected: 1"))->toBeFalse()
        ->and(CorrelationId::isValid("line1\nline2"))->toBeFalse()
        ->and(CorrelationId::isValid('has space'))->toBeFalse()
        ->and(CorrelationId::isValid('<script>'))->toBeFalse();
});

it('reuses a valid request-supplied id and falls back to a generated one otherwise', function (): void {
    $withValidHeader = Request::create('/api/v1/webhooks/github', 'POST');
    $withValidHeader->attributes->set(CorrelationId::REQUEST_ATTRIBUTE, 'client-supplied-id');

    $withoutAttribute = Request::create('/api/v1/webhooks/github', 'POST');

    expect(CorrelationId::fromRequest($withValidHeader))->toBe('client-supplied-id')
        ->and(CorrelationId::fromRequest($withoutAttribute))->not->toBeEmpty();
});
