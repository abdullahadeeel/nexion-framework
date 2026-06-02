<?php

namespace Nexion\Support;

use Nexion\Container\Container;

abstract class ServiceProvider
{
    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Register services in the container.
     */
    abstract public function register(): void;

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
