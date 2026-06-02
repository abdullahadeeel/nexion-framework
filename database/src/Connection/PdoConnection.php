<?php

namespace FlashPHP\Database\Connection;

use PDO;

abstract class PdoConnection extends BaseConnection
{
    /** @var PDO|null */
    protected $connection = null;

    public function query(string $query, array $params = []): array
    {
        $stmt = $this->connection->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function execute(string $query, array $params = []): bool
    {
        $stmt = $this->connection->prepare($query);
        return $stmt->execute($params);
    }

    public function exec(string $query): int|bool
    {
        return $this->connection->exec($query);
    }

    public function lastInsertId(): string|int|null
    {
        return $this->connection->lastInsertId();
    }

    protected function getHost(): string
    {
        return $this->config['host'] ?? '127.0.0.1';
    }

    protected function getPort(string $default): string
    {
        return (string)($this->config['port'] ?? $default);
    }

    protected function getDatabaseName(): string
    {
        return $this->config['dbname'] ?? $this->config['database'] ?? 'phpify';
    }

    protected function getUsername(string $default = 'root'): string
    {
        return $this->config['user'] ?? $this->config['username'] ?? $default;
    }

    protected function getPassword(): string
    {
        return $this->config['password'] ?? '';
    }

    protected function getDefaultOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }
}
