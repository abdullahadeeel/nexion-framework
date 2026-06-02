<?php

namespace FlashPHP\Console\Commands;

use FlashPHP\Console\Command;

class ServeCommand extends Command
{
    protected string $name = 'serve';
    protected string $description = 'Start the development server';

    public function execute(array $args): int
    {
        $host = '127.0.0.1';
        $port = 8000;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--port=')) {
                $port = substr($arg, 7);
            }
            if (str_starts_with($arg, '--host=')) {
                $host = substr($arg, 7);
            }
        }

        $this->info("phpify development server started: http://$host:$port");
        $this->comment("Press Ctrl+C to stop the server.");

        passthru("php -S $host:$port -t public");
        
        return 0;
    }
}
