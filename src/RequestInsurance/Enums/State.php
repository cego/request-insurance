<?php

namespace Cego\RequestInsurance\Enums;

use ReflectionClass;

abstract class State
{
    public const WAITING = 'WAITING';       // A request which is waiting for it's retry_at timestamp to surpass NOW(), before it transitions to READY
    public const READY = 'READY';           // A request that no workers are currently working on (DEFAULT STATE) but is ready for consumption
    public const PENDING = 'PENDING';       // A request which a worker as reserved for consumption, but is yet to begin processing
    public const PROCESSING = 'PROCESSING'; // A request a worker is actively processing
    public const COMPLETED = 'COMPLETED';   // A request that has been processed with a successful response
    public const FAILED = 'FAILED';         // A request that has been processed and received a response or timeout which requires human intervention
    public const ABANDONED = 'ABANDONED';   // A request that has been abandoned, which will not be processed in the future

    /**
     * Returns all constants within the enum
     *
     * @return array
     */
    public static function getAll(): array
    {
        return (new ReflectionClass(__CLASS__))->getConstants();
    }

    public static function getBootstrapColor(string $state): string
    {
        switch ($state) {
            case static::WAITING:
            case static::READY:
                return 'secondary';

            case static::PENDING:
            case static::PROCESSING:
                return 'info';

            case static::COMPLETED:
                return 'success';

            case static::FAILED:
                return 'danger';

            case static::ABANDONED:
                return 'warning';

            default:
                return 'primary';
        }
    }
}
