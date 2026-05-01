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
    private $returnValues = [];

    public function __construct($dbConfig = [])
    {
        parent::__construct($dbConfig);
    }

    public function setReturnValue($method, $value)
    {
        $this->returnValues[$method] = $value;
    }

    private function getReturnValue($method, $default = null)
    {
        return isset($this->returnValues[$method]) ? $this->returnValues[$method] : $default;
    }

    public function getSchemaTool(ItemManager $im, $classReflections, callable $outputFunction = null)
    {
        return $this->getReturnValue('getSchemaTool');
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
        return $this->getReturnValue('queryAndRun');
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
        return $this->getReturnValue('multiQueryAndRun');
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
        return $this->getReturnValue('scanAndRun');
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
        return $this->getReturnValue('parallelScanAndRun');
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
