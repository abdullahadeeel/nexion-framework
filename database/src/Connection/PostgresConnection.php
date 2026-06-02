<?php

namespace FlashPHP\Database\Connection;

use PDO;

class PostgresConnection extends PdoConnection
{
    public function connect(): void
    {
        $host = $this->getHost();
        $port = $this->getPort('5432');
        $dbname = $this->getDatabaseName();
        $user = $this->getUsername('postgres');
        $password = $this->getPassword();

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        
        $this->connection = new PDO($dsn, $user, $password, $this->getDefaultOptions());
    }
}
