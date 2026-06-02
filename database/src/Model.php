<?php

namespace Phpify\Database;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function __isset($key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __unset($key): void
    {
        unset($this->attributes[$key]);
    }

    public static function getTable(): string
    {
        $instance = new static();
        if (isset($instance->table)) {
            return $instance->table;
        }
        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower($class) . 's';
    }

    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::getTable(), static::class);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id): ?static
    {
        $instance = new static();
        return static::query()->where($instance->primaryKey, $id)->first();
    }

    public function save(): bool
    {
        $db = Database::getInstance();
        if ($db === null) {
            throw new \Exception("Database connection not established. Please check your DB configuration.");
        }
        
        $table = static::getTable();
        
        if (isset($this->attributes[$this->primaryKey])) {
            // Update
            $id = $this->attributes[$this->primaryKey];
            $fields = array_diff_key($this->attributes, [$this->primaryKey => '']);
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
            return $db->execute("UPDATE $table SET $set WHERE {$this->primaryKey} = ?", [...array_values($fields), $id]);
        } else {
            // Insert
            $keys = array_keys($this->attributes);
            if (empty($keys)) {
                return false;
            }
            $placeholders = implode(', ', array_fill(0, count($keys), '?'));
            $fields = implode(', ', $keys);
            $result = $db->execute("INSERT INTO $table ($fields) VALUES ($placeholders)", array_values($this->attributes));
            if ($result) {
                $this->attributes[$this->primaryKey] = $db->lastInsertId();
            }
            return $result;
        }
    }

    public function delete(): bool
    {
        if (!isset($this->attributes[$this->primaryKey])) return false;
        
        $db = Database::getInstance();
        if ($db === null) {
            throw new \Exception("Database connection not established. Please check your DB configuration.");
        }

        $table = static::getTable();
        return $db->execute("DELETE FROM $table WHERE {$this->primaryKey} = ?", [$this->attributes[$this->primaryKey]]);
    }

    protected function hasMany(string $relatedClass, string $foreignKey = null, string $localKey = null): array
    {
        $foreignKey = $foreignKey ?? strtolower((new \ReflectionClass(static::class))->getShortName()) . '_id';
        $localKey = $localKey ?? $this->primaryKey;
        
        return $relatedClass::where($foreignKey, $this->$localKey)->get();
    }

    protected function belongsTo(string $relatedClass, string $foreignKey = null, string $ownerKey = null): ?Model
    {
        $instance = new $relatedClass();
        $foreignKey = $foreignKey ?? strtolower((new \ReflectionClass($relatedClass))->getShortName()) . '_id';
        $ownerKey = $ownerKey ?? $instance->primaryKey;
        
        return $relatedClass::where($ownerKey, $this->$foreignKey)->first();
    }

    public static function __callStatic($method, $arguments)
    {
        return static::query()->$method(...$arguments);
    }
}
