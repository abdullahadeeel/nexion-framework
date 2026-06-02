<?php

namespace Phpify\Console;

class Application
{
    protected array $commands = [];

    public function add(Command $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function run(): void
    {
        global $argv;
        $commandName = $argv[1] ?? 'list';

        if ($commandName === 'list' || $commandName === 'help') {
            $this->displayHelp();
            return;
        }

        if (!isset($this->commands[$commandName])) {
            echo "\033[31mCommand '$commandName' not found.\033[0m\n";
            $this->displayHelp();
            return;
        }

        $command = $this->commands[$commandName];
        $args = array_slice($argv, 2);
        
        try {
            exit($command->execute($args));
        } catch (\Exception $e) {
            echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
            exit(1);
        }
    }

    protected function displayHelp(): void
    {
        echo "\033[33mphpify Artisan CLI\033[0m\n\n";
        echo "\033[1mUsage:\033[0m\n";
        echo "  php artisan <command> [options]\n\n";
        echo "\033[1mAvailable commands:\033[0m\n";
        
        foreach ($this->commands as $name => $command) {
            printf("  \033[32m%-20s\033[0m %s\n", $name, $command->getDescription());
        }
        echo "\n";
    }
}
