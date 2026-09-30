<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use InvalidArgumentException;

it('generates a verifier the RFC would accept', function (): void {
    $verifier = CodeVerifier::generate();

    expect(strlen($verifier))->toBe(CodeVerifier::MIN_LENGTH)
        ->and(CodeVerifier::isValid($verifier))->toBeTrue()
        ->and($verifier)->toMatch('/\A[A-Za-z0-9\-._~]+\z/');
});

it('generates a different verifier every time', function (): void {
    $verifiers = [];

    for ($i = 0; $i < 50; $i++) {
        $verifiers[] = CodeVerifier::generate();
    }

    expect(array_unique($verifiers))->toHaveCount(50);
});

it('derives the challenge RFC 7636 appendix B publishes', function (): void {
    // The only fixed point available: the RFC's own worked example. If the
    // digest, the encoding or the padding strip ever changes, this is what
    // notices — every other test here would still pass against a wrong
    // challenge, because both sides would be wrong the same way.
    $verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    expect(CodeVerifier::challengeFor($verifier))
        ->toBe('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM');
});

it('produces a 43-character unpadded base64url challenge', function (): void {
    $challenge = CodeVerifier::challengeFor(CodeVerifier::generate());

    expect(strlen($challenge))->toBe(43)
        ->and($challenge)->not->toContain('=')
        ->and($challenge)->not->toContain('+')
        ->and($challenge)->not->toContain('/');
});

it('accepts the shortest and longest verifiers the RFC allows', function (): void {
    expect(CodeVerifier::isValid(str_repeat('a', CodeVerifier::MIN_LENGTH)))->toBeTrue()
        ->and(CodeVerifier::isValid(str_repeat('a', CodeVerifier::MAX_LENGTH)))->toBeTrue();
});

it('refuses a verifier one character outside either bound', function (): void {
    expect(CodeVerifier::isValid(str_repeat('a', CodeVerifier::MIN_LENGTH - 1)))->toBeFalse()
        ->and(CodeVerifier::isValid(str_repeat('a', CodeVerifier::MAX_LENGTH + 1)))->toBeFalse();
});

it('refuses characters outside the unreserved set', function (): void {
    $base = str_repeat('a', CodeVerifier::MIN_LENGTH - 1);

    expect(CodeVerifier::isValid($base . '+'))->toBeFalse()
        ->and(CodeVerifier::isValid($base . '/'))->toBeFalse()
        ->and(CodeVerifier::isValid($base . '='))->toBeFalse()
        ->and(CodeVerifier::isValid($base . ' '))->toBeFalse();
});

it('refuses to derive a challenge from an invalid verifier', function (): void {
    expect(fn (): string => CodeVerifier::challengeFor('too-short'))
        ->toThrow(InvalidArgumentException::class);
});

it('never puts the verifier in the exception message', function (): void {
    // It is the secret, and this message reaches logs and error trackers.
    $secret = 'short-but-secret';

    try {
        CodeVerifier::challengeFor($secret);
        expect(false)->toBeTrue('expected an InvalidArgumentException');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain($secret);
    }
});
