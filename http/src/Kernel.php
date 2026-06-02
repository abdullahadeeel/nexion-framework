<?php

namespace Phpify\Http;

use Phpify\Foundation\Application;
use Phpify\Routing\Router;

class Kernel
{
    protected Application $app;
    protected Router $router;
    protected array $middleware = [];

    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->sendRequestThroughRouter($request);
        } catch (\Exception $e) {
            return $this->renderException($request, $e);
        }
    }

    protected function sendRequestThroughRouter(Request $request): Response
    {
        $this->app->instance(Request::class, $request);

        $pipeline = array_merge($this->middleware, $this->getRouteMiddleware($request));

        return $this->handleMiddleware($pipeline, $request);
    }

    protected function getRouteMiddleware(Request $request): array
    {
        $resolved = $this->router->resolve($request);
        if (!$resolved) {
            return [];
        }
        [$route, $params] = $resolved;
        return $route->getMiddleware();
    }

    protected function handleMiddleware(array $middleware, Request $request): Response
    {
        $index = 0;

        $next = function (Request $request) use (&$index, $middleware, &$next) {
            if ($index < count($middleware)) {
                $mwClass = $middleware[$index++];
                /** @var \Phpify\Middleware\Middleware $mw */
                $mw = $this->app->make($mwClass);
                return $mw->handle($request, $next);
            }

            return $this->dispatchToRouter($request);
        };

        return $next($request);
    }

    protected function dispatchToRouter(Request $request): Response
    {
        $resolved = $this->router->resolve($request);

        if (!$resolved) {
            return (new Response())->setStatusCode(404)->setContent('404 Not Found');
        }

        [$route, $params] = $resolved;
        $action = $route->getAction();

        if (is_callable($action)) {
            $content = $this->app->call($action, $params);
        } elseif (is_array($action)) {
            [$controller, $method] = $action;
            $content = $this->app->call([$this->app->make($controller), $method], $params);
        } else {
            return (new Response())->setStatusCode(500)->setContent('Invalid action');
        }

        if ($content instanceof Response) {
            return $content;
        }

        return (new Response())->setContent((string)$content);
    }

    protected function renderException(Request $request, \Exception $e): Response
    {
        if ($e instanceof ValidationException && $request->isJson()) {
            return Response::json([
                'message' => $e->getMessage(),
                'errors' => $e->getErrors()
            ], 422);
        }

        $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        
        if (env('APP_DEBUG', false)) {
            $content = "<h1>Uncaught Exception: " . get_class($e) . "</h1>";
            $content .= "<p>" . $e->getMessage() . "</p>";
            $content .= "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            $content = "Server Error";
        }

        return (new Response())->setStatusCode($statusCode)->setContent($content);
    }
}
