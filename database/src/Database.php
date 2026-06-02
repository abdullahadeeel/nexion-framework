<?php

namespace FlashPHP\Database;

use FlashPHP\Database\Connection\ConnectionInterface;
use FlashPHP\Database\Connection\ConnectionFactory;

class Database
{
    protected static ?ConnectionInterface $instance = null;

    /**
     * Connect to database using configuration and DB_CONNECTION environment variable.
     */
    public static function connect(array $config): ConnectionInterface
    {
        if (self::$instance === null) {
            $driver = env('DB_CONNECTION', 'sqlite');
            self::$instance = ConnectionFactory::create($driver, $config);
        }
        return self::$instance;
    }

    /**
     * Get the active connection instance.
     */
    public static function getInstance(): ?ConnectionInterface
    {
        if (self::$instance === null) {
            // Auto-connect using default SQLite driver
            self::connect([]);
        }
        return self::$instance;
    }

    /**
     * Execute a query with parameters and fetch results effectively.
     */
    public static function query(string $query, array $params = []): array
    {
        return self::getInstance()->query($query, $params);
    }

    /**
     * Execute a command with parameters and return success status.
     */
    public static function execute(string $query, array $params = []): bool
    {
        return self::getInstance()->execute($query, $params);
    }
}
