<?php


namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;


use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;

/**
 * Class DynamoDbConnection
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
class DynamoDbConnection implements Connection
{
    /**
     * @var  DynamoDbTable
     */
    protected $dynamodbTable;

    public function __construct($tableName, $dbConfig, $attributeTypes)
    {
        // initialize table
        $this->dynamodbTable = new DynamoDbTable(
            $dbConfig,
            $tableName,
            $attributeTypes
        );
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
        return $this->dynamodbTable->batchGet(
            $keys,
            $isConsistentRead,
            $concurrency,
            $projectedFields,
            $keyIsTyped,
            $retryDelay,
            $maxDelay
        );
    }

    public function batchDelete(array $objs, $concurrency = 10, $maxDelay = 15000)
    {
        $this->dynamodbTable->batchDelete($objs, $concurrency, $maxDelay);
    }

    public function batchPut(array $objs, $concurrency = 10, $maxDelay = 15000)
    {
        $this->dynamodbTable->batchPut($objs, $concurrency, $maxDelay);
    }

    public function set(array $obj, $checkValues = [])
    {
        return $this->dynamodbTable->set($obj, $checkValues);
    }

    public function get(array $keys, $is_consistent_read = false, $projectedFields = [])
    {
        return $this->dynamodbTable->get($keys, $is_consistent_read, $projectedFields);
    }

    public function query(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        return $this->dynamodbTable->query(
            $keyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        );
    }

    public function queryAndRun(
        callable $callback,
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        $this->dynamodbTable->queryAndRun(
            $callback,
            $keyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        );
    }

    public function queryCount(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    ) {
        return $this->dynamodbTable->queryCount(
            $keyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $isConsistentRead,
            $isAscendingOrder
        );
    }

    public function multiQueryAndRun(
        callable $callback,
        $hashKeyName,
        $hashKeyValues,
        $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $filterExpression = '',
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $concurrency = 10,
        $projectedFields = []
    ) {
        $this->dynamodbTable->multiQueryAndRun(
            $callback,
            $hashKeyName,
            $hashKeyValues,
            $rangeKeyConditions,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $filterExpression,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $concurrency,
            $projectedFields
        );
    }

    public function scan(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        &$lastKey = null,
        $evaluationLimit = 30,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        return $this->dynamodbTable->scan(
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        );
    }

    public function scanAndRun(
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        $this->dynamodbTable->scanAndRun(
            $callback,
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        );
    }

    public function parallelScanAndRun(
        $parallel,
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    ) {
        $this->dynamodbTable->parallelScanAndRun(
            $parallel,
            $callback,
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $isConsistentRead,
            $isAscendingOrder,
            $projectedFields
        );
    }

    public function scanCount(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = DynamoDbIndex::PRIMARY_INDEX,
        $isConsistentRead = false,
        $parallel = 10
    ) {
        return $this->dynamodbTable->scanCount(
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $isConsistentRead,
            $parallel
        );
    }
}
