<?php

namespace FlashPHP\Database\Connection;

use FlashPHP\Database\Utils\SqlParser;

class MongodbConnection extends BaseConnection
{
    protected ?string $database = null;
    protected ?string $lastInsertId = null;
    protected bool $isMocked = false;
    protected string $storageDir = '';

    public function connect(): void
    {
        $this->database = $this->getConfig('dbname', $this->getConfig('database', 'phpify'));
        
        if (extension_loaded('mongodb')) {
            $this->connectToManager();
        } else {
            $this->setupMock();
        }
    }

    protected function connectToManager(): void
    {
        $host = $this->getConfig('host', '127.0.0.1');
        $port = $this->getConfig('port', '27017');
        $user = $this->getConfig('user', $this->getConfig('username', ''));
        $password = $this->getConfig('password', '');
        
        $uri = 'mongodb://';
        if ($user && $password) {
            $uri .= urlencode($user) . ':' . urlencode($password) . '@';
        }
        $uri .= "{$host}:{$port}";
        
        try {
            $this->connection = new \MongoDB\Driver\Manager($uri);
            $this->isMocked = false;
        } catch (\Exception $e) {
            $this->setupMock();
        }
    }

    protected function setupMock(): void
    {
        $this->isMocked = true;
        $this->storageDir = $this->getProjectRoot() . '/app/database/mongodb';
        $this->ensureDirectoryExists($this->storageDir);
    }

    public function query(string $query, array $params = []): array
    {
        $parsed = SqlParser::parse($query, $params);
        $table = $parsed['table'];

        if ($parsed['type'] === 'create') {
            return [];
        }

        if ($this->isMocked) {
            return $this->queryMock($parsed);
        }

        return $this->runMongoQuery($parsed);
    }

    protected function runMongoQuery(array $parsed): array
    {
        $table = $parsed['table'];
        $filter = $this->buildFilter($parsed['wheres']);
        $options = $this->buildOptions($parsed);

        $queryObj = new \MongoDB\Driver\Query($filter, $options);
        $cursor = $this->connection->executeQuery("{$this->database}.{$table}", $queryObj);
        
        $results = [];
        foreach ($cursor as $doc) {
            $row = (array)$doc;
            if (isset($row['_id'])) {
                $row['id'] = ($row['_id'] instanceof \MongoDB\BSON\ObjectId) ? (string)$row['_id'] : $row['_id'];
            }
            $results[] = $row;
        }

        return $results;
    }

    protected function buildFilter(array $wheres): array
    {
        $filter = [];
        foreach ($wheres as $w) {
            $col = $w['column'] === 'id' ? '_id' : $w['column'];
            $op = $w['operator'];
            $val = $w['value'];

            if ($op === '=') {
                $filter[$col] = $val;
            } elseif ($op === '!=') {
                $filter[$col] = ['$ne' => $val];
            } elseif ($op === '>') {
                $filter[$col] = ['$gt' => $val];
            } elseif ($op === '>=') {
                $filter[$col] = ['$gte' => $val];
            } elseif ($op === '<') {
                $filter[$col] = ['$lt' => $val];
            } elseif ($op === '<=') {
                $filter[$col] = ['$lte' => $val];
            } elseif ($op === 'LIKE') {
                $pattern = str_replace('%', '.*', preg_quote($val, '/'));
                $filter[$col] = new \MongoDB\BSON\Regex($pattern, 'i');
            }
        }
        return $filter;
    }

    protected function buildOptions(array $parsed): array
    {
        $options = [];
        if (!empty($parsed['orders'])) {
            $sort = [];
            foreach ($parsed['orders'] as $col => $dir) {
                $col = $col === 'id' ? '_id' : $col;
                $sort[$col] = ($dir === 'DESC') ? -1 : 1;
            }
            $options['sort'] = $sort;
        }

        if ($parsed['limit'] !== null) $options['limit'] = $parsed['limit'];
        if ($parsed['offset'] !== null) $options['skip'] = $parsed['offset'];

        return $options;
    }

    public function execute(string $query, array $params = []): bool
    {
        $parsed = SqlParser::parse($query, $params);
        $table = $parsed['table'];

        if ($parsed['type'] === 'create') {
            return true;
        }

        if ($this->isMocked) {
            return $this->executeMock($parsed);
        }

        return $this->runMongoWrite($parsed);
    }

    protected function runMongoWrite(array $parsed): bool
    {
        $table = $parsed['table'];
        $bulk = new \MongoDB\Driver\BulkWrite();

        if ($parsed['type'] === 'insert') {
            $data = $parsed['data'];
            if (!isset($data['_id'])) {
                $data['_id'] = new \MongoDB\BSON\ObjectId();
            }
            $bulk->insert($data);
            $this->connection->executeBulkWrite("{$this->database}.{$table}", $bulk);
            $this->lastInsertId = (string)$data['_id'];
            return true;

        } elseif ($parsed['type'] === 'update') {
            $filter = $this->buildFilter($parsed['wheres']);
            $bulk->update($filter, ['$set' => $parsed['data']], ['multi' => true]);
            $this->connection->executeBulkWrite("{$this->database}.{$table}", $bulk);
            return true;

        } elseif ($parsed['type'] === 'delete') {
            $filter = $this->buildFilter($parsed['wheres']);
            $bulk->delete($filter);
            $this->connection->executeBulkWrite("{$this->database}.{$table}", $bulk);
            return true;
        }

        return false;
    }

    public function exec(string $query): int|bool
    {
        return $this->execute($query) ? 1 : 0;
    }

    public function lastInsertId(): string|int|null
    {
        return $this->lastInsertId;
    }

    /* --- Mock File-based MongoDB Engine --- */

    protected function getMockFile(string $table): string
    {
        return $this->storageDir . '/' . $table . '.json';
    }

    protected function getMockData(string $table): array
    {
        $file = $this->getMockFile($table);
        if (!file_exists($file)) {
            return [];
        }
        return json_decode(file_get_contents($file), true) ?: [];
    }

    protected function saveMockData(string $table, array $data): void
    {
        file_put_contents($this->getMockFile($table), json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function queryMock(array $parsed): array
    {
        $data = $this->getMockData($parsed['table']);
        $filtered = [];

        foreach ($data as $row) {
            if ($this->rowMatches($row, $parsed['wheres'])) {
                $filtered[] = $row;
            }
        }

        if (!empty($parsed['orders'])) {
            $this->sortResults($filtered, $parsed['orders']);
        }

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
            $newId = uniqid('mongo_', true);
            $insertData['id'] = $newId;
            $insertData['_id'] = $newId;
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
