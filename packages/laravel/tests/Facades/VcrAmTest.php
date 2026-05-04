<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\Facades\VcrAm;
use BlobSolutions\VcrAm\VcrClient;

it('resolves to the VcrClient instance bound in the container', function (): void {
    $facadeRoot = VcrAm::getFacadeRoot();
    $containerInstance = $this->app->make(VcrClient::class);

    expect($facadeRoot)
        ->toBeInstanceOf(VcrClient::class)
        ->toBe($containerInstance);
});
