<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Input;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Argument shape for {@see \BlobSolutions\VcrAm\VcrClient::submitSupportReport()}.
 *
 * At least one of `message` and `context` is required.
 *
 * `context` is whatever your side knows that the server does not: versions,
 * configuration, the state of the order, your own journal of recent calls.
 * Leave your customers' personal data out of it; nothing in a diagnosis needs
 * a buyer's name, phone or address. Values under keys that look like
 * credentials are redacted on arrival, which is a safety net and not a reason
 * to send them.
 *
 * The limits are checked here so that a report which cannot be accepted fails
 * before it is sent, with the field named. The size of `context` is checked by
 * the server only: it is measured on the server's own serialisation.
 */
final readonly class SupportReportInput implements JsonSerializable
{
    public const MAX_MESSAGE_LENGTH = 4000;

    public const MAX_CONTACT_LENGTH = 200;

    public const MAX_TRACE_IDS = 50;

    /**
     * @param ?string               $message  What the person saw and expected, in their own words
     * @param ?array<string, mixed> $context  Diagnostics, as a JSON object
     * @param list<string>          $traceIds `VCR-Trace-Id` values of the calls the report is about
     * @param ?string               $contact  Where the person wants the answer, if they said
     */
    public function __construct(
        public ?string $message = null,
        public ?array $context = null,
        public array $traceIds = [],
        public ?string $contact = null,
    ) {
        $hasMessage = $message !== null && trim($message) !== '';
        $hasContext = $context !== null && $context !== [];

        if (! $hasMessage && ! $hasContext) {
            throw new InvalidArgumentException('A support report needs a message, a context, or both.');
        }

        if ($message !== null && mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'message must be at most %d characters, got %d.',
                self::MAX_MESSAGE_LENGTH,
                mb_strlen($message),
            ));
        }

        if ($contact !== null && mb_strlen($contact) > self::MAX_CONTACT_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'contact must be at most %d characters, got %d.',
                self::MAX_CONTACT_LENGTH,
                mb_strlen($contact),
            ));
        }

        if (count($traceIds) > self::MAX_TRACE_IDS) {
            throw new InvalidArgumentException(sprintf(
                'traceIds must hold at most %d entries, got %d.',
                self::MAX_TRACE_IDS,
                count($traceIds),
            ));
        }

        foreach ($traceIds as $traceId) {
            if (preg_match('/\A[0-9a-f]{32}\z/', $traceId) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'traceIds must be 32 lowercase hex characters each, got "%s".',
                    $traceId,
                ));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $payload = [];

        if ($this->message !== null && trim($this->message) !== '') {
            $payload['message'] = $this->message;
        }

        if ($this->contact !== null && trim($this->contact) !== '') {
            $payload['contact'] = $this->contact;
        }

        if ($this->context !== null && $this->context !== []) {
            $payload['context'] = $this->context;
        }

        if ($this->traceIds !== []) {
            $payload['traceIds'] = $this->traceIds;
        }

        return $payload;
    }
}
