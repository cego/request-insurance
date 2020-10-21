<?php

namespace Cego\RequestInsurance\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
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
    protected $description = 'Unlocks request insurances stuck in a locked state';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Unlocking blocked request insurances...');

        RequestInsurance::query()
            ->where('locked_at', '!=', null)
            ->where('locked_at', '<', Carbon::now()->subMinutes(5))
            ->get()
            ->each
            ->unlock();

        $this->info('Unlocking done!');

        return 0;
    }
}
