<?php

namespace Cego\RequestInsurance\Partitioning;

use InvalidArgumentException;

abstract class PartitionGranularity
{
    public const DAILY = 'daily';
    public const WEEKLY = 'weekly';
    public const MONTHLY = 'monthly';

    /** @return array<int, string> */
    public static function all(): array
    {
        return [self::DAILY, self::WEEKLY, self::MONTHLY];
    }

    public static function assertValid(string $granularity): void
    {
        if ( ! in_array($granularity, self::all(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown partition granularity [%s], expected one of [%s]',
                $granularity,
                implode(', ', self::all())
            ));
        }
    }
}
