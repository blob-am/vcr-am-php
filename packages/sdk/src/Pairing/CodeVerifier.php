<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Pairing;

use InvalidArgumentException;

/**
 * The PKCE (RFC 7636) secret behind a pairing request.
 *
 * The store generates a verifier, keeps it on its own server, and registers
 * only `challengeFor()` of it. When the approved code comes back through the
 * merchant's browser, presenting the verifier is what proves the caller is the
 * same store that started the pairing — a code intercepted in the redirect is
 * worthless without it.
 *
 * Only the S256 method exists here. RFC 7636 also defines `plain`, where the
 * challenge *is* the verifier, which would put the secret in the request that
 * is supposed to be protected by it.
 */
final class CodeVerifier
{
    /** RFC 7636 section 4.1. */
    public const MIN_LENGTH = 43;

    /** RFC 7636 section 4.1. */
    public const MAX_LENGTH = 128;

    /**
     * 32 bytes of entropy encode to exactly 43 unreserved characters, which is
     * the RFC's minimum length and its own recommendation.
     */
    private const ENTROPY_BYTES = 32;

    /**
     * The RFC's `unreserved` set: ALPHA / DIGIT / "-" / "." / "_" / "~".
     */
    private const UNRESERVED = '/\A[A-Za-z0-9\-._~]+\z/';

    /**
     * A fresh verifier from the CSPRNG. Store it next to the pending pairing;
     * it is needed once, at the exchange, and is useless afterwards.
     *
     * @return non-empty-string
     */
    public static function generate(): string
    {
        return self::base64Url(random_bytes(self::ENTROPY_BYTES));
    }

    /**
     * The `codeChallenge` to register for a verifier: unpadded
     * base64url(sha256(verifier)), always 43 characters.
     *
     * @return non-empty-string
     */
    public static function challengeFor(string $verifier): string
    {
        self::assertValid($verifier);

        return self::base64Url(hash('sha256', $verifier, binary: true));
    }

    public static function isValid(string $verifier): bool
    {
        $length = strlen($verifier);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            return false;
        }

        return preg_match(self::UNRESERVED, $verifier) === 1;
    }

    private static function assertValid(string $verifier): void
    {
        if (self::isValid($verifier)) {
            return;
        }

        // Deliberately does not echo the verifier: it is the secret, and this
        // message lands in logs and exception trackers.
        throw new InvalidArgumentException(sprintf(
            'A PKCE code verifier must be %d-%d characters of [A-Za-z0-9-._~], got %d characters.',
            self::MIN_LENGTH,
            self::MAX_LENGTH,
            strlen($verifier),
        ));
    }

    /**
     * @return non-empty-string
     */
    private static function base64Url(string $bytes): string
    {
        $encoded = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        assert($encoded !== '');

        return $encoded;
    }
}
