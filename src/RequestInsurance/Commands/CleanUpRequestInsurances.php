<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Cego\RequestInsurance\Models\RequestInsurance;
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
    public function handle()
    {
        // Clean up RequestInsurances table
        RequestInsurance::whereResponseCode(200)->where('created_at', '<', Carbon::now()->subHours(48))->delete();

        // Clean up RequestInsuranceLogs table
        RequestInsuranceLog::doesntHave('parent')->delete();

        return 0;
    }
}
