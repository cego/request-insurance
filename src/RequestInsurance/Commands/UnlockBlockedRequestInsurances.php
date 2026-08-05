<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Cego\RequestInsurance\Enums\State;
use Cego\RequestInsurance\Models\RequestInsurance;

class UnlockBlockedRequestInsurances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unlock:request-insurances';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlocks request insurances stuck in a pending state';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        do {
            $ids = RequestInsurance::query()
                ->select('id')
                ->where('state', State::PENDING)
                ->where('state_changed_at', '<', Carbon::now('UTC')->subMinutes(5))
                ->take(1000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return 0;
            }

            RequestInsurance::query()
                ->whereIn('id', $ids)
                ->where('state', State::PENDING)
                ->where('state_changed_at', '<', Carbon::now('UTC')->subMinutes(5))
                ->update(['state' => State::READY, 'state_changed_at' => Carbon::now('UTC')]);
        } while ($ids->count() === 1000);

        return 0;
    }
}
