<?php

namespace Nexion\Database\Connection;

interface ConnectionInterface
{
    /**
     * Connect to the database.
     */
    public function connect(): void;

    /**
     * Execute a query and return fetched rows as an associative array.
     */
    public function query(string $query, array $params = []): array;

    /**
     * Execute a command (INSERT, UPDATE, DELETE, etc.) and return success.
     */
    public function execute(string $query, array $params = []): bool;

    /**
     * Execute raw SQL or command and return success or affected rows.
     */
    public function exec(string $query): int|bool;

    /**
     * Get the last inserted ID.
     */
    public function lastInsertId(): string|int|null;

    /**
     * Get the raw underlying connection object (e.g. PDO, MongoClient, etc.).
     */
    public function getRawConnection();
}
