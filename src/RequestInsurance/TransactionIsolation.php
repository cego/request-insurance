<?php

namespace Cego\RequestInsurance;

use Cego\RequestInsurance\Models\RequestInsurance;

class TransactionIsolation
{
    public static function readCommittedForNextTransaction(): void
    {
        $connection = resolve(RequestInsurance::class)->getConnection();

        if ($connection->getDriverName() !== 'mysql') {
            return;
        }

        if ($connection->transactionLevel() > 0) {
            return;
        }

        $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
    }
}
