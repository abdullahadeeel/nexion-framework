<?php

namespace Phpify\Console\Commands;

use Phpify\Console\Command;

class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';
    protected string $description = 'Create a new migration file';

    public function execute(array $args): int
    {
        if (empty($args)) {
            $this->error("Not enough arguments (missing: name).");
            return 1;
        }

        $name = $args[0];
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.sql";
        $path = \Phpify\Foundation\Application::$app->getRootPath() . "/database/migrations/$filename";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $content = "-- Migration: $name\n-- Created at: " . date('Y-m-d H:i:s') . "\n\nCREATE TABLE IF NOT EXISTS example (\n    id INT AUTO_INCREMENT PRIMARY KEY\n) ENGINE=INNODB;\n";

        file_put_contents($path, $content);
        $this->info("Migration created successfully: database/migrations/$filename");

        return 0;
    }
}
