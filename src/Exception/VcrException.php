<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm\Exception;

use RuntimeException;

/**
 * Base exception thrown by the VCR.AM SDK. Concrete subclasses describe the
 * specific failure mode (network/transport, server-side API error, response
 * schema mismatch).
 */
abstract class VcrException extends RuntimeException
{
}
