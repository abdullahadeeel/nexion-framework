<?php

namespace Phpify\Database\Utils;

class SqlParser
{
    public static function parse(string $sql, array $params = []): array
    {
        $sql = trim($sql);
        $sqlLower = strtolower($sql);

        if (str_starts_with($sqlLower, 'select')) {
            return self::parseSelect($sql, $params);
        } elseif (str_starts_with($sqlLower, 'insert')) {
            return self::parseInsert($sql, $params);
        } elseif (str_starts_with($sqlLower, 'update')) {
            return self::parseUpdate($sql, $params);
        } elseif (str_starts_with($sqlLower, 'delete')) {
            return self::parseDelete($sql, $params);
        } elseif (str_starts_with($sqlLower, 'create')) {
            return [
                'type' => 'create',
                'table' => self::extractTableName($sqlLower, 'create table'),
                'raw' => $sql
            ];
        }

        return [
            'type' => 'unknown',
            'raw' => $sql
        ];
    }

    protected static function extractTableName(string $sqlLower, string $prefix): string
    {
        $after = substr($sqlLower, strlen($prefix));
        $after = str_replace(['if not exists', 'if exists'], '', $after);
        $after = trim($after);
        if (preg_match('/^([\w]+)/', $after, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }

    protected static function parseSelect(string $sql, array $params): array
    {
        // Regex to parse SELECT: SELECT {fields} FROM {table} [WHERE {where}] [ORDER BY {order}] [LIMIT {limit}] [OFFSET {offset}]
        $pattern = '/^SELECT\s+(.+?)\s+FROM\s+(\w+)(?:\s+WHERE\s+(.+?))?(?:\s+ORDER\s+BY\s+(.+?))?(?:\s+LIMIT\s+(\d+))?(?:\s+OFFSET\s+(\d+))?$/is';
        
        if (preg_match($pattern, $sql, $matches)) {
            $fields = trim($matches[1]);
            $table = trim($matches[2]);
            $whereClause = !empty($matches[3]) ? trim($matches[3]) : '';
            $orderClause = !empty($matches[4]) ? trim($matches[4]) : '';
            $limit = !empty($matches[5]) ? (int)$matches[5] : null;
            $offset = !empty($matches[6]) ? (int)$matches[6] : null;

            $wheres = [];
            if ($whereClause) {
                $wheres = self::parseWhereClause($whereClause, $params);
            }

            $orders = [];
            if ($orderClause) {
                $orderParts = explode(',', $orderClause);
                foreach ($orderParts as $part) {
                    $part = trim($part);
                    $direction = 'ASC';
                    if (preg_match('/^([\w\.]+)\s+(ASC|DESC)$/i', $part, $m)) {
                        $col = $m[1];
                        $direction = strtoupper($m[2]);
                    } else {
                        $col = $part;
                    }
                    $orders[$col] = $direction;
                }
            }

            return [
                'type' => 'select',
                'table' => $table,
                'fields' => $fields === '*' ? [] : array_map('trim', explode(',', $fields)),
                'wheres' => $wheres,
                'orders' => $orders,
                'limit' => $limit,
                'offset' => $offset
            ];
        }

        return ['type' => 'select', 'table' => 'unknown', 'fields' => [], 'wheres' => [], 'orders' => [], 'limit' => null, 'offset' => null];
    }

    protected static function parseInsert(string $sql, array $params): array
    {
        // INSERT INTO table (col1, col2) VALUES (?, ?)
        $pattern = '/^INSERT\s+INTO\s+(\w+)\s*\((.+?)\)\s*VALUES\s*\((.+?)\)$/is';
        if (preg_match($pattern, $sql, $matches)) {
            $table = trim($matches[1]);
            $columns = array_map('trim', explode(',', $matches[2]));
            
            $data = [];
            foreach ($columns as $idx => $col) {
                $data[$col] = $params[$idx] ?? null;
            }

            return [
                'type' => 'insert',
                'table' => $table,
                'data' => $data
            ];
        }

        return ['type' => 'insert', 'table' => 'unknown', 'data' => []];
    }

    protected static function parseUpdate(string $sql, array $params): array
    {
        // UPDATE table SET col1 = ?, col2 = ? WHERE id = ?
        $pattern = '/^UPDATE\s+(\w+)\s+SET\s+(.+?)(?:\s+WHERE\s+(.+?))?$/is';
        if (preg_match($pattern, $sql, $matches)) {
            $table = trim($matches[1]);
            $setClause = trim($matches[2]);
            $whereClause = !empty($matches[3]) ? trim($matches[3]) : '';

            $sets = [];
            $setParts = explode(',', $setClause);
            foreach ($setParts as $part) {
                if (preg_match('/^([\w\.]+)\s*=\s*\?/i', trim($part), $m)) {
                    $sets[] = $m[1];
                }
            }

            $paramIndex = 0;
            $setData = [];
            foreach ($sets as $col) {
                if (array_key_exists($paramIndex, $params)) {
                    $setData[$col] = $params[$paramIndex++];
                }
            }

            $remainingParams = array_slice($params, $paramIndex);
            $wheres = [];
            if ($whereClause) {
                $wheres = self::parseWhereClause($whereClause, $remainingParams);
            }

            return [
                'type' => 'update',
                'table' => $table,
                'data' => $setData,
                'wheres' => $wheres
            ];
        }

        return ['type' => 'update', 'table' => 'unknown', 'data' => [], 'wheres' => []];
    }

    protected static function parseDelete(string $sql, array $params): array
    {
        // DELETE FROM table WHERE id = ?
        $pattern = '/^DELETE\s+FROM\s+(\w+)(?:\s+WHERE\s+(.+?))?$/is';
        if (preg_match($pattern, $sql, $matches)) {
            $table = trim($matches[1]);
            $whereClause = !empty($matches[2]) ? trim($matches[2]) : '';

            $wheres = [];
            if ($whereClause) {
                $wheres = self::parseWhereClause($whereClause, $params);
            }

            return [
                'type' => 'delete',
                'table' => $table,
                'wheres' => $wheres
            ];
        }

        return ['type' => 'delete', 'table' => 'unknown', 'wheres' => []];
    }

    protected static function parseWhereClause(string $whereClause, array $params): array
    {
        $wheres = [];
        $parts = preg_split('/\s+AND\s+/i', $whereClause);
        $paramIndex = 0;

        foreach ($parts as $part) {
            $part = trim($part);
            if (preg_match('/^([\w\.]+)\s*(=|!=|<|>|<=|>=|LIKE)\s*\?/i', $part, $m)) {
                $wheres[] = [
                    'column' => $m[1],
                    'operator' => $m[2],
                    'value' => $params[$paramIndex++] ?? null
                ];
            }
        }

        return $wheres;
    }
}
