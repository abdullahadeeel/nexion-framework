<?php

namespace Nexion\Foundation\Providers;

use Nexion\Support\ServiceProvider;
use Nexion\View\Engine;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Engine::class, function ($app) {
            return new Engine(
                $app->getRootPath() . '/app/Views',
                $app->getRootPath() . '/storage/cache'
            );
        });
    }
}
