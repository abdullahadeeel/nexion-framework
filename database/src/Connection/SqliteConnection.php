<?php

namespace Nexion\Database\Connection;

use PDO;

class SqliteConnection extends PdoConnection
{
    public function connect(): void
    {
        $database = $this->getDatabasePath();
        
        $this->ensureDirectoryExists(dirname($database));
        $this->ensureFileExists($database);

        $this->connection = new PDO("sqlite:{$database}", null, null, $this->getDefaultOptions());
    }

    /**
     * Get the absolute path to the SQLite database file.
     */
    protected function getDatabasePath(): string
    {
        $database = $this->getDatabaseName();
        
        if ($this->isDefaultDatabase($database)) {
            return $this->getProjectRoot() . '/app/database/database.sqlite';
        }

        return $database;
    }

    protected function isDefaultDatabase(?string $database): bool
    {
        $defaultNames = [null, 'phpify', '', 'default'];
        return in_array($database, $defaultNames, true) || !str_contains($database, DIRECTORY_SEPARATOR);
    }

    protected function ensureFileExists(string $path): void
    {
        if ($path !== ':memory:' && !file_exists($path)) {
            touch($path);
        }
    }
}
