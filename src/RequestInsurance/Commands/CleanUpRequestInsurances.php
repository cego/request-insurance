<?php

namespace Cego\RequestInsurance\Commands;

use Illuminate\Console\Command;
use Cego\RequestInsurance\RequestInsuranceCleaner;

class CleanUpRequestInsurances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean:request-insurances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Performs a clean up of all successful requests that are older than 48 hours';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        RequestInsuranceCleaner::cleanUp();

        return 0;
    }
}
