<?php

namespace Phpify\Foundation\Providers;

use Phpify\Support\ServiceProvider;
use Phpify\Routing\Router;
use Phpify\Http\Kernel;

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
