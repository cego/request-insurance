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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle() : int
    {
       $this->updateProcessingRequestInsurances(true);
       $this->updateProcessingRequestInsurances(false);

       return 0;
    }

    /**
     * Updates request insurances that have been processing for more than 10 minutes and logs it.
     * Which state they are updated to is based whether it retries inconsistent states.
     *
     * @param bool $retries_inconsistent
     *
     * @return void
     */
    protected function updateProcessingRequestInsurances(bool $retries_inconsistent) : void
    {
        $reqs = RequestInsurance::query()->where('state', State::PROCESSING)
            ->where("state_changed_at", "<", Carbon::now('UTC')->subMinutes(10))
            ->where("retry_inconsistent", $retries_inconsistent);

        // Get ids of request insurances that have been updated, so they can be included in the log.
        $ids = $reqs->get("id");

        // Update state depending on retries_inconsistent
        $stateChange = $retries_inconsistent ? State::READY : State::FAILED;
        $reqs->update(["state" => $stateChange]);

        Log::info(print("Request insurances with ids $ids , with retry_inconsistent = $retries_inconsistent, were set to state $stateChange, due to processing for too long."));
    }
}