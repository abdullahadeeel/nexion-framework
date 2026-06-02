<?php

namespace Nexion\Foundation\Providers;

use Nexion\Support\ServiceProvider;
use Nexion\Routing\Router;
use Nexion\Http\Kernel;

class RoutingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Router::class, function () {
            return new Router();
        });

        $this->app->singleton(Kernel::class, function ($app) {
            return new Kernel($app, $app->make(Router::class));
        });
    }
}
