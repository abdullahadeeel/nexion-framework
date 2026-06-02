<?php

namespace Nexion\Foundation;

use Nexion\Container\Container;
use Nexion\Http\Request;
use Nexion\Http\Response;
use Nexion\Support\ServiceProvider;

class Application extends Container
{
    public static Application $app;
    protected string $rootPath;
    protected bool $booted = false;
    protected array $serviceProviders = [];

    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        static::setInstance($this);
        static::$app = $this;
        $this->instance('app', $this);
        $this->instance(Application::class, $this);
        $this->instance(Container::class, $this);
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function register(string $provider): void
    {
        if (isset($this->serviceProviders[$provider])) {
            return;
        }

        $instance = new $provider($this);
        $instance->register();
        $this->serviceProviders[$provider] = $instance;

        if ($this->booted) {
            $instance->boot();
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    public function handle(Request $request): Response
    {
        $this->instance(Request::class, $request);
        $this->boot();

        /** @var \Nexion\Http\Kernel $kernel */
        $kernel = $this->make(\Nexion\Http\Kernel::class);
        return $kernel->handle($request);
    }

    public function __get($key)
    {
        $aliases = [
            'router' => \Nexion\Routing\Router::class,
            'db' => \Nexion\Database\Database::class,
            'view' => \Nexion\View\Engine::class,
            'request' => \Nexion\Http\Request::class,
        ];

        if (isset($aliases[$key])) {
            return $this->make($aliases[$key]);
        }

        if (class_exists($key)) {
            return $this->make($key);
        }

        trigger_error("Undefined property: " . static::class . "::\${$key}", E_USER_WARNING);
        return null;
    }
}
