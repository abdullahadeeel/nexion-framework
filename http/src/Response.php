<?php

namespace Nexion\Http;

class Response
{
    protected int $statusCode = 200;
    protected array $headers = [];
    protected string $content = '';

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->content;
    }

    public static function json(array $data, int $status = 200): self
    {
        $response = new self();
        $response->setStatusCode($status);
        $response->setHeader('Content-Type', 'application/json');
        $response->setContent(json_encode($data));
        return $response;
    }

    public static function redirect(string $url, int $status = 302): self
    {
        $response = new self();
        $response->setStatusCode($status);
        $response->setHeader('Location', $url);
        return $response;
    }
}
