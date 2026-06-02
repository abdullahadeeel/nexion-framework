<?php

namespace Nexion\Database\Connection;

abstract class BaseConnection implements ConnectionInterface
{
    protected array $config = [];
    protected $connection = null;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Get the raw underlying connection object.
     */
    public function getRawConnection()
    {
        return $this->connection;
    }

    /**
     * Delegate calls to the raw connection if available (for backward compatibility).
     */
    public function __call($name, $arguments)
    {
        if ($this->connection !== null && method_exists($this->connection, $name)) {
            return $this->connection->$name(...$arguments);
        }
        throw new \BadMethodCallException("Method {$name} does not exist on the database connection.");
    }

    /**
     * Get the project root directory.
     */
    protected function getProjectRoot(): string
    {
        // 4 levels up from phpify-packages/database/src/Connection/
        return dirname(dirname(dirname(dirname(__DIR__))));
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    /**
     * Get a configuration value with a default.
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
