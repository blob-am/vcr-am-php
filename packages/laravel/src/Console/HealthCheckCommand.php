<?php

declare(strict_types=1);

namespace BlobSolutions\LaravelVcrAm\Console;

use BlobSolutions\VcrAm\Exception\VcrException;
use BlobSolutions\VcrAm\VcrClient;
use Illuminate\Console\Command;

final class HealthCheckCommand extends Command
{
    /** @var string */
    protected $signature = 'vcr-am:health';

    /** @var string */
    protected $description = 'Verify VCR.AM API connectivity by listing cashiers';

    public function handle(VcrClient $client): int
    {
        try {
            $cashiers = $client->listCashiers();
        } catch (VcrException $exception) {
            $this->error('VCR.AM API check failed: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('VCR.AM API reachable. Found %d cashier(s).', count($cashiers)));

        return self::SUCCESS;
    }
}
