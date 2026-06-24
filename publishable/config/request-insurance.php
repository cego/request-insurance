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
    | Determines if concurrent http requests are enabled or not
    */

    'concurrentHttpEnabled' => false,

    /*
    | The maximum number of http requests to send concurrently
    */

    'concurrentHttpChunkSize' => 5,

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
     | Exceptions tables ("failed jobs" style). FAILED and ABANDONED request
     | insurances are moved here so the partitioned main tables only ever hold the
     | success lifecycle and whole partitions can be dropped at retention.
     | Default to "{table}_failed" / "{table_logs}_failed" when left null.
     */
    'table_failed'      => null,
    'table_failed_logs' => null,

    'useDbReconnect' => env('REQUEST_INSURANCE_WORKER_USE_DB_RECONNECT', true),

    /*
     | Using skip locked optimizes request insurance to run with multiple worker threads,
     | but is unavailable in mysql versions older than 8.0.0
     */
    'useSkipLocked' => env('REQUEST_INSURANCE_WORKER_USE_SKIP_LOCKED', true),

    /*
    | Partitioning configuration. When the underlying driver supports it
    | (MySQL/MariaDB, PostgreSQL), the request_insurance tables are RANGE
    | partitioned by created_at and retention is performed by dropping whole
    | partitions instead of deleting rows. Unsupported drivers (e.g. sqlite)
    | transparently fall back to a plain table with row-based retention.
    */
    'partitioning' => [
        // Partition size: daily | weekly | monthly
        'granularity' => env('REQUEST_INSURANCE_PARTITION_GRANULARITY', \Cego\RequestInsurance\Partitioning\PartitionGranularity::DAILY),

        // Number of future partitions (in granularity units) to keep pre-created ahead of now
        'precreate_ahead' => (int) env('REQUEST_INSURANCE_PARTITION_PRECREATE_AHEAD', 7),
    ],
];
