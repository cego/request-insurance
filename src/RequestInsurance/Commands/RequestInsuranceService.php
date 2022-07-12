<?php

namespace Cego\RequestInsurance\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Cego\RequestInsurance\RequestInsuranceWorker;

class RequestInsuranceService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:request-insurances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Processes request insurances that are ready to be sent';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     *
     * @return int
     */
    public function handle(): int
    {
        // Bail-out early if request insurance is not enabled
        if (Config::get('request-insurance.enabled') == false) {
            return 0;
        }

        // Run the service
        (new RequestInsuranceWorker())->run();

        return 1;
    }
}
