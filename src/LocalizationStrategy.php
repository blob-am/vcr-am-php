<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

enum LocalizationStrategy: string
{
    case Translation = 'translation';
    case Transliteration = 'transliteration';
}
