<?php

namespace FlashPHP\Routing;

class Route
{
    protected string $method;
    protected string $uri;
    protected $action;
    protected array $middleware = [];
    protected ?string $routeName = null;

    public function __construct(string $method, string $uri, $action)
    {
        $this->method = strtoupper($method);
        $this->uri = '/' . trim($uri, '/');
        $this->action = $action;
    }

    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    public function name(string $name): self
    {
        $this->routeName = $name;
        return $this;
    }

    public function getMethod(): string { return $this->method; }
    public function getUri(): string { return $this->uri; }
    public function getAction() { return $this->action; }
    public function getMiddleware(): array { return $this->middleware; }
    public function getName(): ?string { return $this->routeName; }

    public function matches(string $method, string $uri): ?array
    {
        if ($this->method !== $method) {
            return null;
        }

        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $this->uri);
        $pattern = "#^" . $pattern . "$#";

        if (preg_match($pattern, $uri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    public function generateUrl(array $params = []): string
    {
        $uri = $this->uri;
        foreach ($params as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }
        return $uri;
    }
}
