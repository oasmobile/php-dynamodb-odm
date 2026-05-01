<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-05
 * Time: 14:50
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;

/**
 * Interface Driver
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
interface Connection
{
    public function __construct($dbConfig);

    public function getDatabaseConfig(): array;

    public function getSchemaTool(ItemManager $im, array $classReflections, ?callable $outputFunction = null): AbstractSchemaTool;

    public function setTableName(string $tableName): void;

    public function setAttributeTypes(array $attributeTypes): void;

    public function setItemReflection(ItemReflection $itemReflection): void;

    public function batchGet(
        array $keys,
        bool $isConsistentRead = false,
        int $concurrency = 10,
        array $projectedFields = [],
        bool $keyIsTyped = false,
        int $retryDelay = 0,
        int $maxDelay = 15000
    ): array;

    public function batchDelete(array $objs, int $concurrency = 10, int $maxDelay = 15000): void;

    public function batchPut(array $objs, int $concurrency = 10, int $maxDelay = 15000): void;

    public function set(array $obj, array $checkValues = []): bool;

    public function get(array $keys, bool $is_consistent_read = false, array $projectedFields = []): ?array;

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
    ): array;

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
    ): void;

    public function queryCount(
        string $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        string|bool $indexName = true,
        string $filterExpression = '',
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true
    ): int;

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
    ): void;

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
    ): array;

    public function scanAndRun(
        callable $callback,
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        bool $isConsistentRead = false,
        bool $isAscendingOrder = true,
        array $projectedFields = []
    ): void;

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
    ): void;

    public function scanCount(
        string $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        string|bool $indexName = true,
        bool $isConsistentRead = false,
        int $parallel = 10
    ): int;

}
