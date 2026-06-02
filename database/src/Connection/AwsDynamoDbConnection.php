<?php

namespace Nexion\Database\Connection;

use Nexion\Database\Utils\SqlParser;

class AwsDynamoDbConnection extends BaseConnection
{
    protected ?string $lastInsertId = null;
    protected bool $isMocked = false;
    protected string $storageDir = '';

    public function connect(): void
    {
        if (class_exists('Aws\DynamoDb\DynamoDbClient')) {
            $this->connectToClient();
        } else {
            $this->setupMock();
        }
    }

    protected function connectToClient(): void
    {
        $region = $this->getConfig('region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $key = $this->getConfig('key', env('AWS_ACCESS_KEY_ID', ''));
        $secret = $this->getConfig('secret', env('AWS_SECRET_ACCESS_KEY', ''));
        $endpoint = $this->getConfig('endpoint', env('AWS_DYNAMODB_ENDPOINT', null));

        $args = [
            'region' => $region,
            'version' => '2012-08-10',
        ];

        if ($key && $secret) {
            $args['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        if ($endpoint) {
            $args['endpoint'] = $endpoint;
        }

        try {
            $this->connection = new \Aws\DynamoDb\DynamoDbClient($args);
            $this->isMocked = false;
        } catch (\Exception $e) {
            $this->setupMock();
        }
    }

    protected function setupMock(): void
    {
        $this->isMocked = true;
        $this->storageDir = $this->getProjectRoot() . '/app/database/aws_dynamodb';
        $this->ensureDirectoryExists($this->storageDir);
    }

    public function query(string $query, array $params = []): array
    {
        $parsed = SqlParser::parse($query, $params);
        $table = $parsed['table'];

        if ($parsed['type'] === 'create') return [];

        if ($this->isMocked) return $this->queryMock($parsed);

        return $this->runDynamoQuery($parsed);
    }

    protected function runDynamoQuery(array $parsed): array
    {
        $table = $parsed['table'];
        $scanArgs = ['TableName' => $table];

        $this->applyDynamoFilters($scanArgs, $parsed['wheres']);

        try {
            $result = $this->connection->scan($scanArgs);
            $results = [];
            
            if (isset($result['Items'])) {
                foreach ($result['Items'] as $item) {
                    $results[] = $this->unmarshalItem($item);
                }
            }

            if (!empty($parsed['orders'])) $this->sortResults($results, $parsed['orders']);
            if ($parsed['offset'] !== null || $parsed['limit'] !== null) {
                $results = array_slice($results, $parsed['offset'] ?? 0, $parsed['limit']);
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function applyDynamoFilters(array &$scanArgs, array $wheres): void
    {
        if (empty($wheres)) return;

        $filterExpression = [];
        $expressionAttributeValues = [];
        $expressionAttributeNames = [];

        foreach ($wheres as $idx => $w) {
            $col = $w['column'];
            $op = $w['operator'];
            $val = $w['value'];

            $colKey = "#col_{$idx}";
            $valKey = ":val_{$idx}";

            $expressionAttributeNames[$colKey] = $col;

            if ($op === '=') $filterExpression[] = "{$colKey} = {$valKey}";
            elseif ($op === '!=') $filterExpression[] = "{$colKey} <> {$valKey}";
            elseif ($op === '>') $filterExpression[] = "{$colKey} > {$valKey}";
            elseif ($op === '>=') $filterExpression[] = "{$colKey} >= {$valKey}";
            elseif ($op === '<') $filterExpression[] = "{$colKey} < {$valKey}";
            elseif ($op === '<=') $filterExpression[] = "{$colKey} <= {$valKey}";
            elseif ($op === 'LIKE') $filterExpression[] = "contains({$colKey}, {$valKey})";

            $expressionAttributeValues[$valKey] = $this->marshalValue($val);
        }

        $scanArgs['FilterExpression'] = implode(' AND ', $filterExpression);
        $scanArgs['ExpressionAttributeNames'] = $expressionAttributeNames;
        $scanArgs['ExpressionAttributeValues'] = $expressionAttributeValues;
    }

    public function execute(string $query, array $params = []): bool
    {
        $parsed = SqlParser::parse($query, $params);
        if ($parsed['type'] === 'create') return true;

        if ($this->isMocked) return $this->executeMock($parsed);

        return $this->runDynamoWrite($parsed);
    }

    protected function runDynamoWrite(array $parsed): bool
    {
        try {
            if ($parsed['type'] === 'insert') return $this->handleInsert($parsed);
            if ($parsed['type'] === 'update') return $this->handleUpdate($parsed);
            if ($parsed['type'] === 'delete') return $this->handleDelete($parsed);
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    protected function handleInsert(array $parsed): bool
    {
        $data = $parsed['data'];
        if (!isset($data['id'])) $data['id'] = uniqid('aws_', true);
        
        $marshaled = [];
        foreach ($data as $k => $v) $marshaled[$k] = $this->marshalValue($v);

        $this->connection->putItem([
            'TableName' => $parsed['table'],
            'Item' => $marshaled
        ]);

        $this->lastInsertId = $data['id'];
        return true;
    }

    protected function handleUpdate(array $parsed): bool
    {
        $keys = $this->extractKeys($parsed['wheres']);
        if (empty($keys)) return false;

        $getItemResult = $this->connection->getItem([
            'TableName' => $parsed['table'],
            'Key' => $keys
        ]);

        $currentItem = isset($getItemResult['Item']) ? $this->unmarshalItem($getItemResult['Item']) : [];
        $mergedItem = array_merge($currentItem, $parsed['data']);
        
        $marshaled = [];
        foreach ($mergedItem as $k => $v) $marshaled[$k] = $this->marshalValue($v);

        $this->connection->putItem([
            'TableName' => $parsed['table'],
            'Item' => $marshaled
        ]);

        return true;
    }

    protected function handleDelete(array $parsed): bool
    {
        $keys = $this->extractKeys($parsed['wheres']);
        if (empty($keys)) return false;

        $this->connection->deleteItem([
            'TableName' => $parsed['table'],
            'Key' => $keys
        ]);

        return true;
    }

    protected function extractKeys(array $wheres): array
    {
        $keys = [];
        foreach ($wheres as $w) {
            if ($w['column'] === 'id') $keys['id'] = $this->marshalValue($w['value']);
        }
        return $keys;
    }

    public function exec(string $query): int|bool
    {
        return $this->execute($query) ? 1 : 0;
    }

    public function lastInsertId(): string|int|null
    {
        return $this->lastInsertId;
    }

    /* --- DynamoDB SDK Value Marshaler Helpers --- */

    protected function marshalValue($value): array
    {
        if (is_bool($value)) return ['BOOL' => $value];
        if (is_numeric($value)) return ['N' => (string)$value];
        if ($value === null) return ['NULL' => true];
        return ['S' => (string)$value];
    }

    protected function unmarshalItem(array $item): array
    {
        $unmarshaled = [];
        foreach ($item as $k => $v) {
            $type = key($v);
            $val = current($v);
            if ($type === 'BOOL') $unmarshaled[$k] = (bool)$val;
            elseif ($type === 'N') $unmarshaled[$k] = str_contains((string)$val, '.') ? (float)$val : (int)$val;
            elseif ($type === 'NULL') $unmarshaled[$k] = null;
            else $unmarshaled[$k] = (string)$val;
        }
        return $unmarshaled;
    }

    /* --- Mock File-based AWS DynamoDB Engine --- */

    protected function getMockFile(string $table): string
    {
        return $this->storageDir . '/' . $table . '.json';
    }

    protected function getMockData(string $table): array
    {
        $file = $this->getMockFile($table);
        if (!file_exists($file)) return [];
        return json_decode(file_get_contents($file), true) ?: [];
    }

    protected function saveMockData(string $table, array $data): void
    {
        file_put_contents($this->getMockFile($table), json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function queryMock(array $parsed): array
    {
        $data = $this->getMockData($parsed['table']);
        $filtered = array_filter($data, fn($row) => $this->rowMatches($row, $parsed['wheres']));

        if (!empty($parsed['orders'])) $this->sortResults($filtered, $parsed['orders']);
        if ($parsed['offset'] !== null || $parsed['limit'] !== null) {
            $filtered = array_slice($filtered, $parsed['offset'] ?? 0, $parsed['limit']);
        }

        return $filtered;
    }

    protected function rowMatches(array $row, array $wheres): bool
    {
        foreach ($wheres as $w) {
            $col = $w['column'];
            $op = $w['operator'];
            $val = $w['value'];
            $rowVal = $row[$col] ?? null;

            if ($op === '=') { if ($rowVal != $val) return false; }
            elseif ($op === '!=') { if ($rowVal == $val) return false; }
            elseif ($op === '>') { if ($rowVal <= $val) return false; }
            elseif ($op === '>=') { if ($rowVal < $val) return false; }
            elseif ($op === '<') { if ($rowVal >= $val) return false; }
            elseif ($op === '<=') { if ($rowVal > $val) return false; }
            elseif ($op === 'LIKE') {
                $regex = '/' . str_replace('%', '.*', preg_quote($val, '/')) . '/i';
                if (!preg_match($regex, (string)$rowVal)) return false;
            }
        }
        return true;
    }

    protected function sortResults(array &$results, array $orders): void
    {
        usort($results, function ($a, $b) use ($orders) {
            foreach ($orders as $col => $dir) {
                $aVal = $a[$col] ?? null;
                $bVal = $b[$col] ?? null;
                if ($aVal == $bVal) continue;
                return ($dir === 'DESC') ? ($aVal < $bVal ? 1 : -1) : ($aVal > $bVal ? 1 : -1);
            }
            return 0;
        });
    }

    protected function executeMock(array $parsed): bool
    {
        $table = $parsed['table'];
        $data = $this->getMockData($table);

        if ($parsed['type'] === 'insert') {
            $insertData = $parsed['data'];
            $newId = uniqid('aws_', true);
            $insertData['id'] = $newId;
            $data[] = $insertData;
            $this->saveMockData($table, $data);
            $this->lastInsertId = $newId;
            return true;

        } elseif ($parsed['type'] === 'update') {
            $updated = false;
            foreach ($data as &$row) {
                if ($this->rowMatches($row, $parsed['wheres'])) {
                    $row = array_merge($row, $parsed['data']);
                    $updated = true;
                }
            }
            if ($updated) $this->saveMockData($table, $data);
            return true;

        } elseif ($parsed['type'] === 'delete') {
            $initialCount = count($data);
            $data = array_filter($data, fn($row) => !$this->rowMatches($row, $parsed['wheres']));
            $data = array_values($data);
            if (count($data) !== $initialCount) $this->saveMockData($table, $data);
            return true;
        }

        return false;
    }
}
