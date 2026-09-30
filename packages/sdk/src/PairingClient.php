<?php

declare(strict_types=1);

namespace BlobSolutions\VcrAm;

use BlobSolutions\VcrAm\Exception\VcrApiException;
use BlobSolutions\VcrAm\Exception\VcrNetworkException;
use BlobSolutions\VcrAm\Exception\VcrValidationException;
use BlobSolutions\VcrAm\Http\Transport;
use BlobSolutions\VcrAm\Input\RegisterPairingRequestInput;
use BlobSolutions\VcrAm\Model\PairedRegister;
use BlobSolutions\VcrAm\Model\PairingRequest;
use BlobSolutions\VcrAm\Pairing\CodeVerifier;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Pairs a store with a merchant's register without anyone copying an API key
 * between two browser tabs.
 *
 * Separate from {@see VcrClient} because it takes no API key: obtaining the
 * first one is the whole point, so there is nothing to authenticate with yet.
 * What stands in for authentication is that neither call grants anything on
 * its own — the request is inert until a signed-in merchant approves it
 * against a register they can already manage, and the exchange only succeeds
 * for a caller holding the verifier behind the challenge that was registered.
 *
 * The three calls, all from the store's server:
 *
 * ```php
 * $pairing = new PairingClient(integration: 'my-store/1.0');
 *
 * // 1. Before redirecting the merchant.
 * $verifier = CodeVerifier::generate();
 * $state = bin2hex(random_bytes(16));
 * // persist $verifier and $state against this merchant's session
 *
 * $request = $pairing->registerRequest(new RegisterPairingRequestInput(
 *     redirectUri: 'https://shop.example/wp-admin/admin.php?page=vcr-am',
 *     storeName: 'Example Shop',
 *     state: $state,
 *     codeChallenge: CodeVerifier::challengeFor($verifier),
 * ));
 *
 * // 2. Send the browser to $request->connectUrl.
 *
 * // 3. On the redirect back, having checked `state` matches what was stored:
 * $paired = $pairing->exchangeCode($_GET['code'], $verifier);
 * // persist $paired->apiKey — it is never retrievable again
 * ```
 *
 * @see https://vcr.am
 */
final class PairingClient
{
    public const DEFAULT_BASE_URL = VcrClient::DEFAULT_BASE_URL;

    public readonly string $baseUrl;

    private readonly Transport $transport;

    /**
     * @param ?string $integration A product token naming the store software,
     *                             e.g. `my-plugin/1.2.3 (WordPress/7.1)`. Sent
     *                             as a `User-Agent` token so a merchant's
     *                             support request can be traced to a build.
     */
    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        ?string $integration = null,
    ) {
        $this->transport = new Transport(
            baseUrl: $baseUrl,
            apiKey: null,
            sdkVersion: VcrClient::VERSION,
            integration: $integration === null ? null : Transport::normalizeIntegration($integration),
            httpClient: $httpClient ?? Psr18ClientDiscovery::find(),
            requestFactory: $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            streamFactory: $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            logger: $logger ?? new NullLogger(),
        );
        $this->baseUrl = $this->transport->baseUrl;
    }

    /**
     * Step one: tell VCR.AM a store wants to be paired, and get back the URL
     * to send the merchant to.
     *
     * Grants nothing. The returned request is a row that stays inert unless a
     * merchant approves it, and it expires on its own if they never do.
     *
     * @throws VcrApiException        The request was refused; the message names the field
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function registerRequest(RegisterPairingRequestInput $input): PairingRequest
    {
        $result = $this->transport->request(
            'POST',
            '/connect/requests',
            PairingRequest::class,
            $input->jsonSerialize(),
        );

        assert($result instanceof PairingRequest);

        return $result;
    }

    /**
     * Step two: turn the approved code from the redirect into an API key.
     *
     * Call this from the server, never from the browser — `$codeVerifier` is
     * the secret that proves the caller is the store that started the pairing,
     * and it must never travel to the merchant's machine.
     *
     * The code is single-use and short-lived. Every failure comes back as one
     * `VcrApiException` with the same message on purpose: unknown, spent,
     * expired and wrong-verifier are deliberately indistinguishable, so a
     * stolen code cannot be probed for liveness.
     *
     * @throws InvalidArgumentException `$codeVerifier` is not a PKCE verifier
     * @throws VcrApiException          The code could not be exchanged
     * @throws VcrNetworkException
     * @throws VcrValidationException
     */
    public function exchangeCode(string $code, string $codeVerifier): PairedRegister
    {
        // Caught here rather than at the server, which answers every exchange
        // failure with one indistinguishable message and so cannot tell the
        // caller that the verifier was the malformed half. Same convention the
        // rest of the SDK uses for a caller's own mistake, and the message
        // deliberately does not echo the verifier: it is the secret.
        if (! CodeVerifier::isValid($codeVerifier)) {
            throw new InvalidArgumentException(sprintf(
                'codeVerifier must be %d-%d characters of [A-Za-z0-9-._~], got %d characters.',
                CodeVerifier::MIN_LENGTH,
                CodeVerifier::MAX_LENGTH,
                strlen($codeVerifier),
            ));
        }

        $result = $this->transport->request(
            'POST',
            '/connect/exchange',
            PairedRegister::class,
            ['code' => $code, 'codeVerifier' => $codeVerifier],
        );

        assert($result instanceof PairedRegister);

        return $result;
    }
}
