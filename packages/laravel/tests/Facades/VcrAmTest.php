<?php

declare(strict_types=1);

use BlobSolutions\LaravelVcrAm\Facades\VcrAm;
use BlobSolutions\LaravelVcrAm\VcrAmServiceProvider;
use BlobSolutions\VcrAm\VcrClient;
use RuntimeException;

it('resolves to the VcrClient instance bound in the container', function (): void {
    $facadeRoot = VcrAm::getFacadeRoot();
    $containerInstance = $this->app->make(VcrClient::class);

    expect($facadeRoot)
        ->toBeInstanceOf(VcrClient::class)
        ->toBe($containerInstance);
});

it('returns the sandbox VcrClient when sandbox key is configured', function (): void {
    config()->set('vcr-am.sandbox_api_key', 'sandbox-key');
    $this->app->forgetInstance(VcrAmServiceProvider::SANDBOX_BINDING);

    $sandbox = VcrAm::sandbox();

    expect($sandbox)->toBeInstanceOf(VcrClient::class)
        ->and($sandbox)->not->toBe($this->app->make(VcrClient::class));
});

it('throws a clear RuntimeException when sandbox key is not configured', function (): void {
    config()->set('vcr-am.sandbox_api_key', null);
    $this->app->forgetInstance(VcrAmServiceProvider::SANDBOX_BINDING);

    VcrAm::sandbox();
})->throws(RuntimeException::class, 'VCR_AM_SANDBOX_API_KEY');
