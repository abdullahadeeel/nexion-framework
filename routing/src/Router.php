<?php

namespace Nexion\Routing;

use Nexion\Http\Request;

class Router
{
    protected array $routes = [];
    protected array $groupStack = [];

    public function addRoute(string $method, string $uri, $action): Route
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                if (is_array($group['middleware'])) {
                    $middleware = array_merge($middleware, $group['middleware']);
                } else {
                    $middleware[] = $group['middleware'];
                }
            }
        }

        $fullUri = $prefix . '/' . trim($uri, '/');
        $fullUri = '/' . trim($fullUri, '/');

        $route = new Route($method, $fullUri, $action);
        if (!empty($middleware)) {
            $route->middleware($middleware);
        }

        $this->routes[] = $route;
        return $route;
    }

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $uri, $action): Route { return $this->addRoute('GET', $uri, $action); }
    public function post(string $uri, $action): Route { return $this->addRoute('POST', $uri, $action); }
    public function put(string $uri, $action): Route { return $this->addRoute('PUT', $uri, $action); }
    public function patch(string $uri, $action): Route { return $this->addRoute('PATCH', $uri, $action); }
    public function delete(string $uri, $action): Route { return $this->addRoute('DELETE', $uri, $action); }
    public function options(string $uri, $action): Route { return $this->addRoute('OPTIONS', $uri, $action); }
    
    public function any(string $uri, $action): Route 
    { 
        $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $route = null;
        foreach ($methods as $method) {
            $route = $this->addRoute($method, $uri, $action);
        }
        return $route; 
    }

    public function resolve(Request $request): ?array
    {
        $method = $request->getMethod();
        $uri = $request->getUri();

        foreach ($this->routes as $route) {
            $params = $route->matches($method, $uri);
            if ($params !== null) {
                return [$route, $params];
            }
        }

        return null;
    }

    public function route(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                return $route->generateUrl($params);
            }
        }
        throw new \Exception("Route [{$name}] not defined.");
    }
}
