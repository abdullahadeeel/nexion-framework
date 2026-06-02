<?php

namespace Phpify\Http;

class Request
{
    protected array $queryParams;
    protected array $postData;
    protected array $server;

    public function __construct()
    {
        $this->queryParams = $_GET;
        $this->postData = $_POST;
        $this->server = $_SERVER;

        if ($this->isJson()) {
            $rawInput = file_get_contents('php://input');
            $jsonData = json_decode($rawInput, true);
            if (is_array($jsonData)) {
                $this->postData = array_merge($this->postData, $jsonData);
            }
        }
    }

    public function isJson(): bool
    {
        $contentType = $this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? '';
        return str_contains(strtolower($contentType), 'application/json');
    }

    public function getMethod(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function getUri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return rtrim($path, '/') ?: '/';
    }

    public function input(string $key, $default = null)
    {
        return $this->postData[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->postData);
    }

    public function validate(array $rules): array
    {
        $data = $this->all();
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $fieldRules = explode('|', $ruleString);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$ruleName, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $rule;
                }

                if ($ruleName === 'required') {
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $errors[$field][] = "The " . str_replace('_', ' ', $field) . " field is required.";
                    }
                } elseif ($value !== null && $value !== '') {
                    if ($ruleName === 'email') {
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must be a valid email address.";
                        }
                    } elseif ($ruleName === 'numeric') {
                        if (!is_numeric($value)) {
                            $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must be a number.";
                        }
                    } elseif ($ruleName === 'min') {
                        $minVal = (int)$params[0];
                        if (is_numeric($value)) {
                            if ($value < $minVal) {
                                $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must be at least {$minVal}.";
                            }
                        } else {
                            if (strlen((string)$value) < $minVal) {
                                $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must be at least {$minVal} characters.";
                            }
                        }
                    } elseif ($ruleName === 'max') {
                        $maxVal = (int)$params[0];
                        if (is_numeric($value)) {
                            if ($value > $maxVal) {
                                $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must not be greater than {$maxVal}.";
                            }
                        } else {
                            if (strlen((string)$value) > $maxVal) {
                                $errors[$field][] = "The " . str_replace('_', ' ', $field) . " must not be greater than {$maxVal} characters.";
                            }
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return array_intersect_key($data, array_flip(array_keys($rules)));
    }
}
