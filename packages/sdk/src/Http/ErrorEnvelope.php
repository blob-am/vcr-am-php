<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Http;

use BlobSolutions\VcrAm\Model\ApiErrorIssue;
use BlobSolutions\VcrAm\Model\PendingResource;
use JsonException;

/**
 * Reads the API's error body, `{ error, issues?, requestId?, pending? }`.
 *
 * Every field is treated as optional even though the server always sends
 * `error`, because this runs on bodies that may not have come from VCR at all —
 * a proxy's HTML error page, a gateway timeout. Anything unrecognised yields
 * nulls and the caller falls back to the raw body: parsing an error must never
 * throw a second error over the first, so nothing in here can fail.
 *
 * @internal
 */
final class ErrorEnvelope
{
    /**
     * @param list<ApiErrorIssue> $issues
     */
    private function __construct(
        public readonly ?string $message,
        public readonly array $issues,
        public readonly ?string $requestId,
        public readonly ?PendingResource $pending,
    ) {
    }

    public static function parse(string $rawBody): self
    {
        try {
            $decoded = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return self::empty();
        }

        if (! is_array($decoded)) {
            return self::empty();
        }

        $message = $decoded['error'] ?? null;
        $requestId = $decoded['requestId'] ?? null;

        return new self(
            is_string($message) ? $message : null,
            self::parseIssues($decoded['issues'] ?? null),
            is_string($requestId) ? $requestId : null,
            self::parsePending($decoded['pending'] ?? null),
        );
    }

    private static function empty(): self
    {
        return new self(null, [], null, null);
    }

    /**
     * @return list<ApiErrorIssue>
     */
    private static function parseIssues(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $issues = [];

        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $message = $entry['message'] ?? null;
            $code = $entry['code'] ?? null;
            $path = $entry['path'] ?? null;

            if (! is_string($message) || ! is_string($code)) {
                continue;
            }

            $segments = [];

            if (is_array($path)) {
                foreach ($path as $segment) {
                    if (is_string($segment) || is_int($segment)) {
                        $segments[] = $segment;
                    }
                }
            }

            $issues[] = new ApiErrorIssue($segments, $message, $code);
        }

        return $issues;
    }

    private static function parsePending(mixed $raw): ?PendingResource
    {
        if (! is_array($raw)) {
            return null;
        }

        $type = $raw['type'] ?? null;
        $id = $raw['id'] ?? null;
        $statusUrl = $raw['statusUrl'] ?? null;

        // All three or nothing: a half-parsed handle would point the caller at
        // a document that may not be the one that was persisted, and the whole
        // purpose of this field is telling them not to resend.
        if (! is_string($type) || ! is_int($id) || ! is_string($statusUrl)) {
            return null;
        }

        // Not part of that rule: a server older than this field sends no
        // `mayResubmit`, and refusing the handle over it would throw away the
        // id and the poll URL those servers do send. Stays null, which the
        // model documents as "assume false".
        $mayResubmit = $raw['mayResubmit'] ?? null;

        return new PendingResource(
            $type,
            $id,
            $statusUrl,
            is_bool($mayResubmit) ? $mayResubmit : null,
        );
    }
}
