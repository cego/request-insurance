<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class FailOrReadyProcessingRequestInsurances extends Command
{
    /**
     * Name and signature of the console command
     *
     * @var string
     */
    protected $signature = 'request-insurance:unstuck-processing';

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
    public function handle(): int
    {
        $this->unstuckProcessingRequestInsurances();

        return 0;
    }

    /**
     * Updates state of request insurances that have been processing for more than 10 minutes and logs for each RI.
     * Which state they are updated to is based whether it retries inconsistent states.
     *
     * @return void
     */
    protected function unstuckProcessingRequestInsurances(): void
    {
        RequestInsurance::query()->where('state', State::PROCESSING)
            ->where('state_changed_at', '<', Carbon::now('UTC')->subMinutes(10))
            ->get()
            ->each(function (RequestInsurance $requestInsurance) {
                // State is updated based on retry_inconsistent
                $stateChange = $requestInsurance->retry_inconsistent ? State::READY : State::FAILED;
                $requestInsurance->update(['state' => $stateChange, 'state_changed_at' => Carbon::now('UTC')]);

                Log::info("Request insurance with id $requestInsurance->id was updated to $stateChange due to processing for too long.");
            });
    }
}
