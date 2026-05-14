<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Console;

use BlobSolutions\VcrAm\Exception\VcrException;
use BlobSolutions\VcrAm\Model\AccountInfo;
use BlobSolutions\VcrAm\VcrClient;
use BlobSolutions\VcrAm\VcrMode;
use Illuminate\Console\Command;

final class HealthCheckCommand extends Command
{
    /** @var string */
    protected $signature = 'vcr-am:health
                            {--sandbox : Hit the sandbox client instead of the default}';

    /** @var string */
    protected $description = 'Verify VCR.AM API connectivity and report which VCR the key talks to';

    public function handle(): int
    {
        $useSandbox = $this->option('sandbox') === true;

        try {
            /** @var VcrClient $client */
            $client = $this->laravel->make(
                $useSandbox
                    ? \BlobSolutions\LaravelVcrAm\VcrAmServiceProvider::SANDBOX_BINDING
                    : VcrClient::class,
            );

            $identity = $client->whoami();
        } catch (VcrException $exception) {
            $this->error('VCR.AM API check failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->renderIdentity($identity);

        return self::SUCCESS;
    }

    private function renderIdentity(AccountInfo $identity): void
    {
        $modeLabel = $identity->mode === VcrMode::Sandbox ? 'sandbox' : 'production';
        $name = $identity->tradingPlatformName !== '' ? $identity->tradingPlatformName : '(unnamed VCR)';
        $crn = $identity->crn !== null ? 'CRN ' . $identity->crn : 'CRN not activated';
        $entity = $identity->businessEntity->name !== '' ? $identity->businessEntity->name : '(unnamed entity)';

        // One write per line — Laravel's `expectsOutputToContain` checks each
        // doWrite call independently, and CLI consumers prefer scannable
        // key-value pairs over a long single line anyway.
        $this->info('VCR.AM API reachable.');
        $this->line(sprintf('  VCR    : "%s" (%s)', $name, $crn));
        $this->line(sprintf('  Mode   : %s', $modeLabel));
        $this->line(sprintf('  Entity : %s (TIN %s)', $entity, $identity->businessEntity->tin));
    }
}
