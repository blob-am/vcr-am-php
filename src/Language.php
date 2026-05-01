<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

/**
 * Language code as recognised by the VCR.AM API.
 *
 * The VCR.AM API uses ISO 639-1 codes for the three Armenian-supported
 * languages plus the `multi` discriminator. The `multi` value appears on
 * detail responses where a localisation entry covers more than one language.
 */
enum Language: string
{
    case Armenian = 'hy';
    case Russian = 'ru';
    case English = 'en';
    case Multi = 'multi';
}
