<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Http;

use BlobSolutions\VcrAm\Diagnostics\RequestAttempt;
use BlobSolutions\VcrAm\Diagnostics\RequestObserver;
use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns the end of one call into a {@see RequestAttempt} and hands it to the
 * integrator's observer.
 *
 * @internal
 */
final class AttemptRecorder
{
    private const NANOSECONDS_PER_MILLISECOND = 1_000_000;

    private const MILLISECONDS_PER_SECOND = 1000;

    private readonly DateTimeImmutable $startedAt;

    /** @var array{0: int, 1: int} Monotonic clock: seconds, nanoseconds */
    private readonly array $startedClock;

    public function __construct(
        private readonly ?RequestObserver $observer,
        private readonly LoggerInterface $logger,
        private readonly string $method,
        private readonly string $path,
        private readonly ?string $idempotencyKey,
    ) {
        $this->startedAt = new DateTimeImmutable();
        $this->startedClock = hrtime();
    }

    public function succeeded(ResponseInterface $response): void
    {
        $this->record($response, null);
    }

    public function failed(VcrApiException|VcrNetworkException|VcrValidationException $failure): void
    {
        if ($failure instanceof VcrApiException) {
            $this->record(
                $failure->response,
                $failure->apiErrorMessage ?? sprintf('HTTP %d', $failure->statusCode),
            );

            return;
        }

        if ($failure instanceof VcrValidationException) {
            $this->record($failure->response, $failure->getMessage());

            return;
        }

        // HTTP clients quote the URL they could not reach, query string and
        // all, and the query string is where a customer reference travels.
        $this->record(null, str_replace(
            (string) $failure->request->getUri(),
            $this->path,
            $failure->getMessage(),
        ));
    }

    private function elapsedMs(): int
    {
        [$seconds, $nanoseconds] = hrtime();
        [$startedSeconds, $startedNanoseconds] = $this->startedClock;

        return ($seconds - $startedSeconds) * self::MILLISECONDS_PER_SECOND
            + intdiv($nanoseconds - $startedNanoseconds, self::NANOSECONDS_PER_MILLISECOND);
    }

    private function record(?ResponseInterface $response, ?string $failure): void
    {
        if ($this->observer === null) {
            return;
        }

        $traceId = $response?->getHeaderLine(RequestAttempt::TRACE_ID_HEADER);

        $attempt = new RequestAttempt(
            startedAt: $this->startedAt,
            method: $this->method,
            path: $this->path,
            durationMs: $this->elapsedMs(),
            statusCode: $response?->getStatusCode(),
            traceId: $traceId === null || $traceId === '' ? null : $traceId,
            idempotencyKey: $this->idempotencyKey,
            failure: $failure,
        );

        try {
            $this->observer->onRequestAttempt($attempt);
        } catch (Throwable $e) {
            // The call has an outcome already and the caller must get it: see
            // RequestObserver for why this is not rethrown.
            $this->logger->error('VCR.AM request observer failed', [
                'method' => $this->method,
                'path' => $this->path,
                'traceId' => $attempt->traceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
