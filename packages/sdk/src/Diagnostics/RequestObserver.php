<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Diagnostics;

/**
 * Told about every call a client makes, once it has ended either way.
 *
 * Meant for a durable journal on the integrator's side: when a merchant says
 * "the receipt never came out", the journal shows whether a request was sent
 * at all, and its trace id points at the server's record of the same call.
 *
 * Called after the outcome is known and before it is returned or thrown. An
 * exception escaping from the observer is caught and reported to the client's
 * logger, never rethrown: a failed journal write must not turn a registered
 * receipt into an apparent failure that the caller then retries.
 */
interface RequestObserver
{
    public function onRequestAttempt(RequestAttempt $attempt): void;
}
