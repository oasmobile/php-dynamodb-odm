<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\AbstractDbConnection;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Stub Connection for unit testing without DynamoDB.
 * Extends AbstractDbConnection to get basic implementations,
 * and stubs out all abstract/remaining methods.
 */
class StubConnection extends AbstractDbConnection
{
    /** @var array Configurable return values for method calls */
    private $returnValues = [];

    /** @var array Queued return values (for methods called multiple times) */
    private $returnQueue = [];

    /** @var array Data rows for callback-based methods (queryAndRun, scanAndRun, etc.) */
    private $callbackData = [];

    public function __construct($dbConfig = [])
    {
        parent::__construct($dbConfig);
    }

    public function setReturnValue($method, $value)
    {
        $this->returnValues[$method] = $value;
    }

    /**
     * Queue multiple return values for a method. Each call pops one value.
     * When the queue is exhausted, falls back to setReturnValue or default.
     */
    public function queueReturnValues($method, array $values)
    {
        $this->returnQueue[$method] = $values;
    }

    /**
     * Set data rows that callback-based methods will iterate over.
     */
    public function setCallbackData($method, array $rows)
    {
        $this->callbackData[$method] = $rows;
    }

    private function getReturnValue($method, $default = null)
    {
        if (isset($this->returnQueue[$method]) && !empty($this->returnQueue[$method])) {
            return array_shift($this->returnQueue[$method]);
        }
        return isset($this->returnValues[$method]) ? $this->returnValues[$method] : $default;
    }

    private function getCallbackData($method)
    {
        return isset($this->callbackData[$method]) ? $this->callbackData[$method] : [];
    }

    public function getSchemaTool(ItemManager $im, $classReflections, callable $outputFunction = null)
    {
        $val = $this->getReturnValue('getSchemaTool');
        if ($val !== null) {
            return $val;
        }
        return new StubSchemaTool($im, $classReflections, $outputFunction);
    }

    public function batchGet(
        array $keys,
        $isConsistentRead = false,
        $concurrency = 10,
        $projectedFields = [],
        $keyIsTyped = false,
        $retryDelay = 0,
        $maxDelay = 15000
    ) {
        return $this->getReturnValue('batchGet', []);
    }

    public function batchDelete(array $objs, $concurrency = 10, $maxDelay = 15000)
    {
        return $this->getReturnValue('batchDelete', true);
    }

    public function batchPut(array $objs, $concurrency = 10, $maxDelay = 15000)
    {
        return $this->getReturnValue('batchPut', true);
    }

    public function set(array $obj, $checkValues = [])
    {
        return $this->getReturnValue('set', true);
    }

    public function get(array $keys, $is_consistent_read = false, $projectedFields = [])
    {
        return $this->getReturnValue('get');
    }

    public function query(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = true,
        $filterExpression = '',
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        return $this->getReturnValue('query', []);
    }

    public function queryAndRun(
        callable $callback,
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = true,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        foreach ($this->getCallbackData('queryAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function queryCount(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = true,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        return $this->getReturnValue('queryCount', 0);
    }

    public function multiQueryAndRun(
        callable $callback,
        $hashKeyName,
        $hashKeyValues,
        $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = true,
        $filterExpression = '',
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $concurrency = 10,
        $projectedFields = []
    ) {
        foreach ($this->getCallbackData('multiQueryAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function scan(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        return $this->getReturnValue('scan', []);
    }

    public function scanAndRun(
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        foreach ($this->getCallbackData('scanAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function parallelScanAndRun(
        $parallel,
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        foreach ($this->getCallbackData('parallelScanAndRun') as $row) {
            $result = call_user_func($callback, $row);
            if ($result === false) {
                break;
            }
        }
    }

    public function scanCount(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        $isConsistentRead = false,
        $parallel = 10
    ) {
        return $this->getReturnValue('scanCount', 0);
    }
}
