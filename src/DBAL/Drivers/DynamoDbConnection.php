<?php
declare(strict_types=1);
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
    private ?DynamoDbTable $dynamodbTable = null;

    protected function getDynamodbTable(): DynamoDbTable
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
        bool $isConsistentRead = false,
        int $concurrency = 10,
        array $projectedFields = [],
        bool $keyIsTyped = false,
        int $retryDelay = 0,
        int $maxDelay = 15000
    ): array {
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

    public function batchDelete(array $objs, int $concurrency = 10, int $maxDelay = 15000): void
    {
        $this->getDynamodbTable()->batchDelete($objs, $concurrency, $maxDelay);
    }

    public function batchPut(array $objs, int $concurrency = 10, int $maxDelay = 15000): void
    {
        $this->getDynamodbTable()->batchPut($objs, $concurrency, $maxDelay);
    }

    public function set(array $obj, array $checkValues = []): bool
    {
        return $this->getDynamodbTable()->set($obj, $checkValues);
    }

    public function get(array $keys, bool $is_consistent_read = false, array $projectedFields = []): ?array
    {
        return $this->getDynamodbTable()->get($keys, $is_consistent_read, $projectedFields);
    }

    public function query(
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        string $filterExpression = '',
        mixed &$lastKey = null,
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): array {
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
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        string $filterExpression = '',
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
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
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        string $filterExpression = '',
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true
    ): int {
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
        string $hashKeyName,
        array $hashKeyValues,
        string $rangeKeyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        string $filterExpression = '',
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        int $concurrency = 10,
        array $projectedFields = []
    ): void {
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
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        mixed &$lastKey = null,
        int $evaluationLimit = 30,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): array {
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
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
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
        int $parallel,
        callable $callback,
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void {
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
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = DynamoDbIndex::PRIMARY_INDEX,
        bool $isConsistentRead = false,
        int $parallel = 10
    ): int {
        return $this->getDynamodbTable()->scanCount(
            $filterExpression,
            $fieldsMapping,
            $paramsMapping,
            $indexName,
            $isConsistentRead,
            $parallel
        );
    }

    public function getSchemaTool(ItemManager $im, array $classReflections, ?callable $outputFunction = null): DynamoDbSchemaTool
    {
        return new DynamoDbSchemaTool($im, $classReflections, $outputFunction);
    }
}
