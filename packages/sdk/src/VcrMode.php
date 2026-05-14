<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Operating mode of a Virtual Cash Register.
 *
 * - `Production` — calls flow through to Armenia's State Revenue Committee and
 *   issue real fiscal receipts.
 * - `Sandbox` — same API surface, but receipts are mocked locally; nothing is
 *   sent to the SRC. Use sandbox keys for development, CI, and demos.
 */
enum VcrMode: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
}
