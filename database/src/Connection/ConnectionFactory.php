<?php

namespace Nexion\Database\Connection;

class ConnectionFactory
{
    public static function create(string $driver, array $config): ConnectionInterface
    {
        switch (strtolower($driver)) {
            case 'sqlite':
                return new SqliteConnection($config);
            case 'mysql':
            case 'mariadb':
                return new MysqlConnection($config);
            case 'pgsql':
            case 'postgres':
            case 'postgresql':
                return new PostgresConnection($config);
            case 'mongodb':
            case 'mongo':
                return new MongodbConnection($config);
            case 'aws':
            case 'dynamodb':
            case 'aws_dynamodb':
                return new AwsDynamoDbConnection($config);
            default:
                throw new \InvalidArgumentException("Unsupported database driver: {$driver}");
        }
    }
}
