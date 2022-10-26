<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Support\Facades\Log;


class FailOrReadyProcessingRequestInsurances extends Command
{
    /**
     * Name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'fail-or-ready:request-insurances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sets requests that have been processing for at least 10 minutes into either failed or ready state';

    public function handle() : int
    {
       $this->queryDatabase(true, State::READY);
       $this->queryDatabase(false, State::FAILED);

       return 0;
    }

    protected function queryDatabase(bool $retries_inconsistent, string $stateChange) : void
    {
        $reqs = RequestInsurance::query()
            ->where("state", State::PROCESSING)
            ->where("state_changed_at", "<", Carbon::now('UTC')->subMinutes(10))
            ->where("retry_inconsistent", '=', $retries_inconsistent);

        $reqs->update(["state" => $stateChange]);

        // Get ids of request insurances that have been updated.
        $ids = $reqs->get("id");
        Log::info(print("Request insurances with ids $ids that have been processing for 10 minutes since shutdown, with retry_inconsistent = $retries_inconsistent, were set to state $stateChange"));
    }
}