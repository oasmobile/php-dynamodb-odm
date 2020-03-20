<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-06
 * Time: 12:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;


use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\DynamoDbSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Class DynamoDbConnection
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
class DynamoDbConnection extends AbstractDbConnection
{
    /**
     * @var DynamoDbTable
     */
    private $dynamodbTable = null;

    /**
     * @return DynamoDbTable
     */
    protected function getDynamodbTable()
    {
        if ($this->dynamodbTable !== null) {
            return $this->dynamodbTable;
        }

        if (empty($this->tableName)) {
            throw new ODMException("Unknown table name to initialize DynamoDbTable client");
        }

        if (empty($this->attributeTypes)) {
            throw new ODMException("Unknown attribute types to initialize DynamoDbTable client");
        }

        $this->dynamodbTable = new DynamoDbTable(
            $this->dbConfig,
            $this->tableName,
            $this->attributeTypes
        );

        return $this->dynamodbTable;
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
        return $this->getDynamodbTable()->batchGet(
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
        $this->getDynamodbTable()->batchDelete($objs, $concurrency, $maxDelay);
    }

    public function batchPut(array $objs, $concurrency = 10, $maxDelay = 15000)
    {
        $this->getDynamodbTable()->batchPut($objs, $concurrency, $maxDelay);
    }

    public function set(array $obj, $checkValues = [])
    {
        return $this->getDynamodbTable()->set($obj, $checkValues);
    }

    public function get(array $keys, $is_consistent_read = false, $projectedFields = [])
    {
        return $this->getDynamodbTable()->get($keys, $is_consistent_read, $projectedFields);
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
        return $this->getDynamodbTable()->query(
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
        $this->getDynamodbTable()->queryAndRun(
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
        return $this->getDynamodbTable()->queryCount(
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
        $this->getDynamodbTable()->multiQueryAndRun(
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
        return $this->getDynamodbTable()->scan(
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
        $this->getDynamodbTable()->scanAndRun(
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
        $this->getDynamodbTable()->parallelScanAndRun(
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
        return $this->getDynamodbTable()->scanCount(
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $isConsistentRead,
            $parallel
        );
    }

    /**
     * @param  ItemManager  $im
     * @param $classReflections
     * @param  callable|null  $outputFunction
     * @return DynamoDbSchemaTool
     */
    public function getSchemaTool(ItemManager $im, $classReflections, callable $outputFunction = null)
    {
        return new DynamoDbSchemaTool($im, $classReflections, $outputFunction);
    }
}
