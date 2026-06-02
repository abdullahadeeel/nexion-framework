<?php

namespace Nexion\Console\Commands;

use Nexion\Console\Command;
use Nexion\Database\Database;

class MigrateCommand extends Command
{
    protected string $name = 'migrate';
    protected string $description = 'Run the database migrations';

    public function execute(array $args): int
    {
        $this->info("Running migrations...");

        $config = [
            'host' => env('DB_HOST', '127.0.0.1'),
            'dbname' => env('DB_DATABASE', 'phpify'),
            'user' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', '')
        ];

        try {
            $db = Database::connect($config);
            
            // Create migrations table if it doesn't exist
            $driver = env('DB_CONNECTION', 'sqlite');
            if ($driver === 'sqlite') {
                $db->exec("CREATE TABLE IF NOT EXISTS migrations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );");
            } else {
                $db->exec("CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=INNODB;");
            }

            $migrationFiles = glob(\Nexion\Foundation\Application::$app->getRootPath() . '/database/migrations/*.sql');
            
            if (empty($migrationFiles)) {
                $this->comment("No migrations found.");
                return 0;
            }

            // Get already executed migrations
            $results = $db->query("SELECT migration FROM migrations");
            $executed = array_column($results, 'migration');

            foreach ($migrationFiles as $file) {
                $filename = basename($file);
                if (in_array($filename, $executed)) {
                    continue;
                }

                $this->comment("Migrating: $filename");
                $sql = file_get_contents($file);
                $db->exec($sql);
                
                $db->execute("INSERT INTO migrations (migration) VALUES (?)", [$filename]);
                
                $this->info("Migrated:  $filename");
            }

            $this->info("Migrations completed successfully.");
            return 0;

        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            return 1;
        }
    }
}
