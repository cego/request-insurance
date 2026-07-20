<?php

namespace Tests\Support;

use Testcontainers\Modules\MariaDBContainer;
use Testcontainers\Modules\PostgresContainer;
use Testcontainers\Container\StartedTestContainer;

class TestDatabaseContainer
{
    private static ?StartedTestContainer $container = null;

    public static function startFromEnvironment(): void
    {
        $engine = getenv('TEST_DATABASE') ?: null;

        if ($engine === null) {
            return;
        }

        $ownerPid = getmypid();

        if ($engine === 'pgsql') {
            self::$container = (new PostgresContainer('16', 'postgres', 'postgres', 'testing'))->start();
            self::setEnvironment([
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST'       => self::$container->getHost(),
                'DB_PORT'       => (string) self::$container->getFirstMappedPort(),
                'DB_DATABASE'   => 'testing',
                'DB_USERNAME'   => 'postgres',
                'DB_PASSWORD'   => 'postgres',
            ]);
        } elseif ($engine === 'mariadb') {
            self::$container = (new MariaDBContainer('11.4', 'root'))
                ->withMariaDBDatabase('testing')
                ->withMariaDBUser('testing', 'testing')
                ->start();
            self::setEnvironment([
                'DB_CONNECTION' => 'mysql',
                'DB_HOST'       => self::$container->getHost(),
                'DB_PORT'       => (string) self::$container->getFirstMappedPort(),
                'DB_DATABASE'   => 'testing',
                'DB_USERNAME'   => 'testing',
                'DB_PASSWORD'   => 'testing',
            ]);
        } else {
            throw new \InvalidArgumentException("Unsupported TEST_DATABASE value: {$engine}");
        }

        register_shutdown_function(function () use ($ownerPid) {
            // Forked concurrency-test children inherit shutdown functions. Only the
            // process that started the suite container is allowed to stop it.
            if (getmypid() === $ownerPid && self::$container !== null) {
                self::$container->stop();
                self::$container = null;
            }
        });
    }

    /** @param array<string, string> $values */
    private static function setEnvironment(array $values): void
    {
        foreach ($values as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
