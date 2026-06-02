<?php

namespace Nexion\Console;

abstract class Command
{
    protected string $name;
    protected string $description = '';

    abstract public function execute(array $args): int;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function info(string $message): void
    {
        echo "\033[32m$message\033[0m\n";
    }

    protected function error(string $message): void
    {
        echo "\033[31m$message\033[0m\n";
    }

    protected function comment(string $message): void
    {
        echo "\033[33m$message\033[0m\n";
    }
}
