<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\AutoSettleTender;
use BlobSolutions\VcrAm\Input\AutoSettle;

it('serializes a cash auto-settle to the wire format', function (): void {
    $autoSettle = new AutoSettle(AutoSettleTender::Cash);

    expect(json_encode($autoSettle, JSON_THROW_ON_ERROR))
        ->toBe(json_encode(['tender' => 'cash'], JSON_THROW_ON_ERROR));
});

it('serializes a non-cash auto-settle to the wire format', function (): void {
    $autoSettle = new AutoSettle(AutoSettleTender::NonCash);

    expect(json_encode($autoSettle, JSON_THROW_ON_ERROR))
        ->toBe(json_encode(['tender' => 'nonCash'], JSON_THROW_ON_ERROR));
});
