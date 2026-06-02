<?php

namespace Nexion\Foundation\Providers;

use Nexion\Support\ServiceProvider;
use Nexion\Database\Database;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Database::class, function () {
            return new Database();
        });
    }

    public function boot(): void
    {
        $config = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'phpify'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'driver' => env('DB_CONNECTION', 'sqlite')
        ];

        Database::connect($config);
    }
}
