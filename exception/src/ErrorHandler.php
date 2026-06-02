<?php

namespace Phpify\Exception;

use Throwable;
use Phpify\Http\Response;

class ErrorHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
    }

    public static function handleException(Throwable $exception): void
    {
        $isCli = PHP_SAPI === 'cli';
        $isJson = false;
        if (!$isCli && isset(\Phpify\Foundation\Application::$app)) {
            $isJson = \Phpify\Foundation\Application::$app->request->isJson();
        }

        $statusCode = 500;
        if ($exception instanceof \Phpify\Http\ValidationException) {
            $statusCode = 422;
        }

        // CLI mode: plain text output
        if ($isCli) {
            $class = get_class($exception);
            echo "\n\033[31m[{$class}]\033[0m " . $exception->getMessage() . "\n";
            echo "  File: " . $exception->getFile() . " on line " . $exception->getLine() . "\n";
            echo "\nStack Trace:\n" . $exception->getTraceAsString() . "\n";
            exit(1);
        }

        if ($isJson) {
            $data = [
                'message' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString())
            ];
            if ($exception instanceof \Phpify\Http\ValidationException) {
                $data['errors'] = $exception->getErrors();
            }
            Response::json($data, $statusCode)->send();
            return;
        }

        // Gorgeous HTML error page
        http_response_code($statusCode);
        $message = htmlspecialchars($exception->getMessage());
        $class = get_class($exception);
        $file = htmlspecialchars($exception->getFile());
        $line = $exception->getLine();
        $trace = nl2br(htmlspecialchars($exception->getTraceAsString()));

        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Error: {$message}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f7f9fa; color: #333; margin: 0; padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); padding: 40px; border-top: 5px solid #e74c3c; }
        h1 { font-size: 24px; color: #c0392b; margin-top: 0; }
        .class-name { font-size: 14px; text-transform: uppercase; color: #7f8c8d; font-weight: bold; }
        .meta { background: #fdf2f2; border: 1px solid #f5c6cb; border-radius: 4px; padding: 15px; margin: 20px 0; font-family: monospace; font-size: 14px; color: #721c24; }
        .trace-container { margin-top: 30px; }
        h3 { color: #2c3e50; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        pre { background: #2d3748; color: #f7fafc; padding: 20px; border-radius: 6px; overflow-x: auto; font-family: 'Courier New', Courier, monospace; font-size: 13px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="container">
        <div class="class-name">{$class}</div>
        <h1>{$message}</h1>
        <div class="meta">
            <strong>File:</strong> {$file}<br>
            <strong>Line:</strong> {$line}
        </div>
        <div class="trace-container">
            <h3>Stack Trace</h3>
            <pre>{$trace}</pre>
        </div>
    </div>
</body>
</html>
HTML;
        exit(1);
    }

    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
}
