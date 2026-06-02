<?php

namespace Nexion\Config;

class Dotenv
{
    public static function load(string $path): void
    {
        if (is_dir($path)) {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
        }
    }
}
