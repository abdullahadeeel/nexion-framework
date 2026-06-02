<?php

namespace FlashPHP\Database;

class QueryBuilder
{
    protected string $table;
    protected string $modelClass;
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;

    public function __construct(string $table, string $modelClass)
    {
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function where(string $column, string $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->wheres[] = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = "$column " . strtoupper($direction);
        return $this;
    }

    public function limit(int $value): self
    {
        $this->limit = $value;
        return $this;
    }

    public function offset(int $value): self
    {
        $this->offset = $value;
        return $this;
    }

    public function get(): array
    {
        $db = Database::getInstance();
        if ($db === null) {
            throw new \Exception("Database connection not established. Please check your DB configuration.");
        }

        $sql = "SELECT * FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        $results = $db->query($sql, $this->bindings);

        $modelClass = $this->modelClass;
        return array_map(fn($row) => new $modelClass($row), $results);
    }

    public function first(): ?Model
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }
}
