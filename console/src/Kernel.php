<?php

namespace Phpify\Console;

use Phpify\Foundation\Application as BaseApplication;
use Phpify\Console\Command;

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
