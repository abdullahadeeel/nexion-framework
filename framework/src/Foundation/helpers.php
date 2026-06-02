<?php

use Nexion\Foundation\Application;
use Nexion\Http\Response;

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;
        return $value;
    }
}

if (!function_exists('app')) {
    function app(string $abstract = null, array $parameters = [])
    {
        if (is_null($abstract)) {
            return \Nexion\Container\Container::getInstance();
        }

        return \Nexion\Container\Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('request')) {
    function request()
    {
        return app(\Nexion\Http\Request::class);
    }
}

if (!function_exists('response')) {
    function response()
    {
        return new Response();
    }
}

if (!function_exists('view')) {
    function view(string $name, array $data = [])
    {
        return app(\Nexion\View\Engine::class)->render($name, $data);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302)
    {
        return Response::redirect($url, $status);
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = [])
    {
        return app(\Nexion\Routing\Router::class)->route($name, $params);
    }
}

if (!function_exists('dd')) {
    function dd(...$vars)
    {
        if (PHP_SAPI === 'cli') {
            foreach ($vars as $var) {
                var_dump($var);
            }
        } else {
            echo "<pre style='background: #1a1a2e; color: #e94560; padding: 20px; border-radius: 8px; font-family: monospace; font-size: 14px; overflow-x: auto; border-left: 5px solid #00adb5; box-shadow: 0 4px 10px rgba(0,0,0,0.15); margin: 20px;'>";
            foreach ($vars as $var) {
                var_dump($var);
            }
            echo "</pre>";
        }
        die(1);
    }
}
