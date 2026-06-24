<?php

namespace Cego\RequestInsurance\Partitioning;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\ConnectionInterface;

class PartitionManagerFactory
{
    public static function for(ConnectionInterface $connection): PartitionManager
    {
        $granularity = Config::get('request-insurance.partitioning.granularity', PartitionGranularity::DAILY);
        $ahead = (int) Config::get('request-insurance.partitioning.precreate_ahead', 7);

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => new MySqlPartitionManager($connection, $granularity, $ahead),
            'pgsql' => new PostgresPartitionManager($connection, $granularity, $ahead),
            default => new UnsupportedPartitionManager($connection, $granularity, $ahead),
        };
    }
}
