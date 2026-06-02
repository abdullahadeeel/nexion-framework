<?php

namespace FlashPHP\Database\Connection;

use PDO;

class MysqlConnection extends PdoConnection
{
    public function connect(): void
    {
        $host = $this->getHost();
        $port = $this->getPort('3306');
        $dbname = $this->getDatabaseName();
        $user = $this->getUsername();
        $password = $this->getPassword();

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        $this->connection = new PDO($dsn, $user, $password, $this->getDefaultOptions());
    }
}
