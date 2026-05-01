<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\AbstractDbConnection;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Stub Connection for unit testing without DynamoDB.
 * Extends AbstractDbConnection to get basic implementations,
 * and stubs out all abstract/remaining methods.
 */
class StubConnection extends AbstractDbConnection
{
    /** @var array Configurable return values for method calls */
    private array $returnValues = [];

    /** @var array Queued return values (for methods called multiple times) */
    private array $returnQueue = [];

    /** @var array Data rows for callback-based methods (queryAndRun, scanAndRun, etc.) */
    private array $callbackData = [];

    public function __construct($dbConfig = [])
    {
        parent::__construct($dbConfig);
    }

    public function setReturnValue(string $method, mixed $value): void
    {
        $this->returnValues[$method] = $value;
    }

    /**
     * Queue multiple return values for a method. Each call pops one value.
     * When the queue is exhausted, falls back to setReturnValue or default.
     */
    public function queueReturnValues(string $method, array $values): void
    {
        $this->returnQueue[$method] = $values;
    }

    /**
     * Set data rows that callback-based methods will iterate over.
     */
    public function setCallbackData(string $method, array $rows): void
    {
        $this->callbackData[$method] = $rows;
    }

    private function getReturnValue(string $method, mixed $default = null): mixed
    {
        if (isset($this->returnQueue[$method]) && !empty($this->returnQueue[$method])) {
            return array_shift($this->returnQueue[$method]);
        }
        return isset($this->returnValues[$method]) ? $this->returnValues[$method] : $default;
    }

    private function getCallbackData(string $method): array
    {
        return isset($this->callbackData[$method]) ? $this->callbackData[$method] : [];
    }

    public function getSchemaTool(ItemManager $im, array $classReflections, ?callable $outputFunction = null): AbstractSchemaTool
    {
        $val = $this->getReturnValue('getSchemaTool');
        if ($val !== null) {
            return $val;
        }
        return new StubSchemaTool($im, $classReflections, $outputFunction);
    }

    public function batchGet(
        array $keys,
        bool $isConsistentRead = false,
        int $concurrency = 10,
        array $projectedFields = [],
        bool $keyIsTyped = false,
        int $retryDelay = 0,
        int $maxDelay = 15000
    ): array {
        return $this->getReturnValue('batchGet', []);
    }

    public function batchDelete(array $objs, int $concurrency = 10, int $maxDelay = 15000): void
    {
        $this->getReturnValue('batchDelete', true);
    }

    public function batchPut(array $objs, int $concurrency = 10, int $maxDelay = 15000): void
    {
        $this->getReturnValue('batchPut', true);
    }

    public function set(array $obj, array $checkValues = []): bool
    {
        return $this->getReturnValue('set', true);
    }

    public function get(array $keys, bool $is_consistent_read = false, array $projectedFields = []): ?array
    {
        return $this->getReturnValue('get');
    }

    public function query(
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = true,
        string $filterExpression = '',
        mixed &$lastKey = null,
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): array {
        return $this->getReturnValue('query', []);
    }

    public function queryAndRun(
        callable $callback,
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = true,
        string $filterExpression = '',
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
        foreach ($this->getCallbackData('queryAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function queryCount(
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = true,
        string $filterExpression = '',
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true
    ): int {
        return $this->getReturnValue('queryCount', 0);
    }

    public function multiQueryAndRun(
        callable $callback,
        string $hashKeyName,
        array $hashKeyValues,
        string $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = true,
        string $filterExpression = '',
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        int $concurrency = 10,
        array $projectedFields = []
    ): void {
        foreach ($this->getCallbackData('multiQueryAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function scan(
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        mixed &$lastKey = null,
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): array {
        return $this->getReturnValue('scan', []);
    }

    public function scanAndRun(
        callable $callback,
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
        foreach ($this->getCallbackData('scanAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function parallelScanAndRun(
        int $parallel,
        callable $callback,
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
        foreach ($this->getCallbackData('parallelScanAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function scanCount(
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        bool $isConsistentRead = false,
        int $parallel = 10
    ): int {
        return $this->getReturnValue('scanCount', 0);
    }
}
