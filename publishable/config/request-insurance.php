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

    'microSecondsToWait' => 2000000,


    /*
    | Set the maximum number of retires before backing off completely
    */

    'maximumNumberOfRetries' => 10,

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
        'headers' => ['Authorization', 'authorization']
    ],

    /*
     | Sets the table name to look for request insurances
     */
    'table'        => null,
    'table_logs'   => null,
];
