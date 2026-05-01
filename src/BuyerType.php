<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

enum BuyerType: string
{
    case Individual = 'individual';
    case BusinessEntity = 'business_entity';
}
