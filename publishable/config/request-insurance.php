<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Insurance
    |--------------------------------------------------------------------------
    |
    | Here you can enable RequestInsurance or disable it. Once enabled the
    | RequestInsuranceService command can start processing requests
    |
    */

    'enabled' => env('REQUEST_INSURANCE_ENABLED', true),

    /*
    | Sets the default value for the retry_inconsistent option on new request insurances.
    | When enabled, request insurances that end up in an inconsistent state are retried by
    | default instead of failed. This can still be overridden per request via the builder.
    */

    'retryInconsistentDefault' => env('REQUEST_INSURANCE_RETRY_INCONSISTENT_DEFAULT', false),

    /*
    | Sets if keep alive should be sent with curl requests
    */

    'keepAlive' => true,

    /*
    | Sets the timeout for a curl request, this is the time execute() has to complete the requests
    */

    'timeoutInSeconds' => 20,

    /*
    | Set the amount of microseconds to wait between each run cycle
    */

    'microSecondsToWait' => env('REQUEST_INSURANCE_WORKER_MICRO_SECONDS_INTERVAL', 200000),

    /*
    | Set the maximum number of retries before backing off completely
    */

    'maximumNumberOfRetries' => 20,

    /*
    | The number of days to keep completed rows, before deletion
    */

    'cleanUpKeepDays' => 14,

    /*
    | The number of rows to chunk delete between slight delays, if you experience OOM errors, then reduce this number.
    */

    'cleanChunkSize' => 1000,

    /*
    | Set the number of requests in each batch
    */

    'batchSize' => env('REQUEST_INSURANCE_BATCH_SIZE', 100),

    /*
    | The maximum number of http requests to send concurrently.
    |
    | Null sends the entire batch at once. A value of 1 sends requests one at a time,
    | in priority order, at the cost of a worker cycle long enough to do so - see
    | maximumSecondsPerWorkerCycle.
    */

    'concurrentHttpChunkSize' => env('REQUEST_INSURANCE_CONCURRENT_HTTP_CHUNK_SIZE'),

    /*
    | Sets how long a single worker cycle may take, before the worker considers itself
    | stuck and exits. A cycle must be able to send a full batch within the budget:
    |
    |     ceil(batchSize / concurrentHttpChunkSize) * timeoutInSeconds
    */

    'maximumSecondsPerWorkerCycle' => env('REQUEST_INSURANCE_MAX_SECONDS_PER_WORKER_CYCLE', 120),

    /*
     | Set the concrete implementation for HttpRequest
     */

    'httpRequestClass' => env('REQUEST_INSURANCE_HTTP_REQUEST_CLASS', \Cego\RequestInsurance\CurlRequest::class),

    /*
    | Sets if load should be condensed to a value between 0 and 1, and have values above 1 being overload
    | if false value will accumulate from all running instances. E.g. 3 instances will give a value
    | between 0 and 3 for normal load, and above for overload
    */

    'condenseLoad' => env('REQUEST_INSURANCE_CONDENSE_LOAD', true),

    /*
    | Sets the fields which should always be encrypted.
    */

    'fieldsToAutoEncrypt' => [
        'headers' => ['Authorization', 'authorization'],
    ],

    /*
     | Sets the table name to look for request insurances
     */
    'table'                => null,
    'table_logs'           => null,
    'table_edits'          => null,
    'table_edit_approvals' => null,

    /*
     | Sets if the worker should reconnect to the database at the start of every cycle.
     |
     | Lost connections are detected and recovered from either way, so this is only needed
     | to stop workers from holding on to a connection they should not keep - such as one
     | pinned to a single node behind a load balancer.
     */

    'useDbReconnect' => env('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT', false),

    /*
     | Using skip locked optimizes request insurance to run with multiple worker threads,
     | but is unavailable in mysql versions older than 8.0.0
     */
    'useSkipLocked' => env('REQUEST_INSURANCE_WORKER_USE_SKIP_LOCKED', true),

    'useForceIndex' => env('REQUEST_INSURANCE_WORKER_USE_FORCE_INDEX', true),

    'forceIndexName' => env('REQUEST_INSURANCE_WORKER_FORCE_INDEX_NAME'),
];
