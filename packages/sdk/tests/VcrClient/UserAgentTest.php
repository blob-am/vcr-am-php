<?php

declare(strict_types=1);

use BlobSolutions\VcrAm\VcrClient;
use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * Builds a client with an `$integration` token and returns the request it sends.
 */
function requestWithIntegration(?string $integration): RequestInterface
{
    $mockClient = new MockClient();
    $factory = new Psr17Factory();
    $client = new VcrClient(
        apiKey: 'test-key',
        httpClient: $mockClient,
        requestFactory: $factory,
        streamFactory: $factory,
        integration: $integration,
    );

    $mockClient->addResponse(new Response(200, ['Content-Type' => 'application/json'], '[]'));
    $client->listCashiers();

    $request = $mockClient->getLastRequest();
    assert($request instanceof RequestInterface);

    return $request;
}

it('announces its own version, which is the one in the changelog', function (): void {
    // The constant went through 0.8.0 and 0.9.0 still saying 0.7.0, so the
    // server's request log could not name the client version it was serving.
    // The newest released heading, `## [x.y.z] — date`, is the version of
    // record; the release workflow checks the same constant against the tag.
    $changelog = file_get_contents(__DIR__ . '/../../CHANGELOG.md');

    if ($changelog === false) {
        throw new RuntimeException('CHANGELOG.md could not be read.');
    }

    if (preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $changelog, $matches) !== 1) {
        throw new RuntimeException('CHANGELOG.md has no "## [x.y.z]" heading to compare VERSION against.');
    }

    expect(VcrClient::VERSION)->toBe($matches[1]);
});

it('sends its own product token when nothing embeds it', function (): void {
    $userAgent = requestWithIntegration(null)->getHeaderLine('User-Agent');

    expect($userAgent)->toBe(sprintf(
        'vcr-am-sdk-php/%s (+https://github.com/blob-am/vcr-am-sdk-php)',
        VcrClient::VERSION,
    ));
});

it('appends the embedding integration after its own token', function (): void {
    $integration = 'vcr-am-woocommerce/0.1.8 (WordPress/7.1; WooCommerce/11.1; PHP/8.3)';

    $userAgent = requestWithIntegration($integration)->getHeaderLine('User-Agent');

    expect($userAgent)->toStartWith('vcr-am-sdk-php/' . VcrClient::VERSION)
        ->and($userAgent)->toEndWith(' ' . $integration);
});

it('trims the integration token', function (): void {
    $userAgent = requestWithIntegration("  some-app/1.0\t")->getHeaderLine('User-Agent');

    expect($userAgent)->toEndWith(' some-app/1.0');
});

it('refuses an integration token carrying a newline', function (): void {
    // Header injection. On WordPress the plugin version reaching this call is
    // whatever the last filter on it returned, so it is not trusted input.
    expect(fn (): RequestInterface => requestWithIntegration("evil/1.0\r\nX-Api-Key: stolen"))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses an empty or oversized integration token', function (): void {
    expect(fn (): RequestInterface => requestWithIntegration('   '))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): RequestInterface => requestWithIntegration(str_repeat('a', VcrClient::MAX_INTEGRATION_LENGTH + 1)))
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a non-ASCII integration token', function (): void {
    expect(fn (): RequestInterface => requestWithIntegration('խանութ/1.0'))
        ->toThrow(InvalidArgumentException::class);
});
