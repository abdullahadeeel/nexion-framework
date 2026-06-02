<?php

namespace Phpify\Foundation\Providers;

use Phpify\Support\ServiceProvider;
use Phpify\View\Engine;

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
