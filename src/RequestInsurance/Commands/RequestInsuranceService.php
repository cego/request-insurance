<?php

namespace Cego\RequestInsurance\Commands;

use Throwable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
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
     * Once set, holds the number of microseconds to wait between each cycle
     *
     * @var int $microSecondsToWait
     */
    protected $microSecondsToWait;

    /**
     * Holds all recorded time series data
     *
     * @var array[] $timeSeries
     */
    protected $timeSeries = [
        'five'    => [],
        'ten'     => [],
        'fifteen' => [],
    ];

    /**
     * Holds a hash created when the instance started running
     *
     * @var string $runningHash
     */
    protected $runningHash;

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
        (new RequestInsuranceWorker)->run();

        return 1;
    }

//    /**
//     * Records and calculates the current load
//     *
//     * @param int $executionTime
//     */
//    protected function recordLoadDataPoint($executionTime)
//    {
//        // This is really a poor implementation of keeping records segregated in five, ten and fifteen minutes
//        // As we base the number of records we want to keep on the assumption of how many cycles will
//        // approximately run within these intervals. This is done to keep the clean up logic within
//        // simple array instructions and have the calculations have no need for timestamps
//        // as a result this should perform better
//        $numberOfEntriesForFiveMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 5;
//        $numberOfEntriesForTenMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 10;
//        $numberOfEntriesForFifteenMinutes = (1000000 / min($this->microSecondsToWait, 100000)) * 60 * 15;
//
//        // Clean up five minute series
//        while (count($this->timeSeries['five']) >= $numberOfEntriesForFiveMinutes) {
//            array_shift($this->timeSeries['five']);
//        }
//
//        // Clean up ten minute series
//        while (count($this->timeSeries['ten']) >= $numberOfEntriesForTenMinutes) {
//            array_shift($this->timeSeries['ten']);
//        }
//
//        // Clean up fifteen minute series
//        while (count($this->timeSeries['fifteen']) >= $numberOfEntriesForFifteenMinutes) {
//            array_shift($this->timeSeries['fifteen']);
//        }
//
//        // Record the execution time
//        $this->timeSeries['five'][] = $executionTime;
//        $this->timeSeries['ten'][] = $executionTime;
//        $this->timeSeries['fifteen'][] = $executionTime;
//
//        // Calculate mean load
//        $fiveMinuteLoad = (array_sum($this->timeSeries['five']) / count($this->timeSeries['five'])) / 1000000;
//        $tenMinuteLoad = (array_sum($this->timeSeries['ten']) / count($this->timeSeries['ten'])) / 1000000;
//        $fifteenMinuteLoad = (array_sum($this->timeSeries['fifteen']) / count($this->timeSeries['fifteen'])) / 1000000;
//
//        $json = json_encode([
//            'loadFiveMinutes'    => $fiveMinuteLoad,
//            'loadTenMinutes'     => $tenMinuteLoad,
//            'loadFifteenMinutes' => $fifteenMinuteLoad,
//        ]);
//
//        $filePath = sprintf('load-statistics/load-%s.json', $this->runningHash);
//
//        Storage::disk('local')->put($filePath, $json);
//    }
//
//    /**
//     * Cleans up the load statistics files
//     *
//     * @return void
//     */
//    protected function cleanUpLoadStatistics()
//    {
//        if ( ! Storage::disk('local')->exists('load-statistics')) {
//            Storage::disk('local')->makeDirectory('load-statistics');
//
//            return;
//        }
//
//        // Delete old files as they will not be relevant
//        $files = Storage::disk('local')->files('load-statistics');
//        Storage::disk('local')->delete($files);
//    }
}
