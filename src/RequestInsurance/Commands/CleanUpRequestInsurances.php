<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\Models\RequestInsurance;
use Cego\RequestInsurance\RequestInsuranceCleaner;
use Cego\RequestInsurance\Models\RequestInsuranceLog;

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
