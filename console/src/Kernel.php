<?php

namespace FlashPHP\Console;

use FlashPHP\Foundation\Application as BaseApplication;
use FlashPHP\Console\Command;

class Kernel
{
    protected BaseApplication $app;
    protected Application $console;

    public function __construct(BaseApplication $app, Application $console)
    {
        $this->app = $app;
        $this->console = $console;
    }

    public function handle($input, $output = null): int
    {
        $this->app->boot();
        $this->console->run();
        return 0;
    }
}
