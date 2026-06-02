<?php

namespace FlashPHP\Foundation\Providers;

use FlashPHP\Support\ServiceProvider;
use FlashPHP\Routing\Router;
use FlashPHP\Http\Kernel;

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
