<?php

namespace Cego\RequestInsurance\Partitioning;

use Illuminate\Support\Facades\Config;
use Illuminate\Database\ConnectionInterface;

class PartitionManagerFactory
{
    public static function for(ConnectionInterface $connection): PartitionManager
    {
        $ahead = (int) Config::get('request-insurance.partitioning.precreate_ahead', 7);

        return match ($connection->getDriverName()) {
            'mysql', 'mariadb' => new MySqlPartitionManager($connection, $ahead),
            'pgsql'            => new PostgresPartitionManager($connection, $ahead),
            default            => new UnsupportedPartitionManager($connection, $ahead),
        };
    }
}
