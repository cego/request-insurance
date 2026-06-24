<?php

namespace Cego\RequestInsurance\Partitioning;

use RuntimeException;

/**
 * Thrown when a partition whose range has aged out of the retention window still
 * holds a row that is not safe to drop (e.g. a non-COMPLETED request that should
 * have been extracted to the exceptions tables). Retention fails loud rather than
 * silently dropping a row that still needs attention.
 */
class PartitionNotDroppableException extends RuntimeException
{
}
