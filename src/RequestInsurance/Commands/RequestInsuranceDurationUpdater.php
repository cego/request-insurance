<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;
use Illuminate\Console\Command;
use Cego\RequestInsurance\RequestInsuranceCleaner;

class RequestInsuranceDurationUpdater
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:duration-not-completed-request-insurance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Performs an update on duration for all request insurances that are not completed';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $notCompletedStates = [State::READY, State::PROCESSING, State::PENDING, State::WAITING];
        $requests = RequestInsurance::query()
            ->whereIn("state", $notCompletedStates);

        foreach ($requests as $request) {
            $request->duration = Carbon::now('UTC')->diffInMinutes($request->createdAt);
            dd($request);

            $request->save();
        }

        return 0;
    }
}