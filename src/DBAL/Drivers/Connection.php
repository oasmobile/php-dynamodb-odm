<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-05
 * Time: 14:50
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchemaTool;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Interface Driver
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
interface Connection
{
    public function __construct($dbConfig);

    public function getDatabaseConfig();

    /**
     * @param  ItemManager  $im
     * @param $classReflections
     * @param  callable|null  $outputFunction
     * @return AbstractSchemaTool
     */
    public function getSchemaTool(ItemManager $im, $classReflections, callable $outputFunction = null);

    public function setTableName($tableName);

    public function setAttributeTypes($tableName);

    public function batchGet(
        array $keys,
        $isConsistentRead = false,
        $concurrency = 10,
        $projectedFields = [],
        $keyIsTyped = false,
        $retryDelay = 0,
        $maxDelay = 15000
    );

    public function batchDelete(array $objs, $concurrency = 10, $maxDelay = 15000);

    public function batchPut(array $objs, $concurrency = 10, $maxDelay = 15000);

    public function set(array $obj, $checkValues = []);

    public function get(array $keys, $is_consistent_read = false, $projectedFields = []);

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
    );

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
    );

    public function queryCount(
        $keyConditions,
        array $fieldsMapping,
        array $paramsMapping,
        $indexName = true,
        $filterExpression = '',
        $isConsistentRead = false,
        $isAscendingOrder = true
    );

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
    );

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
    );

    public function scanAndRun(
        callable $callback,
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        $isConsistentRead = false,
        $isAscendingOrder = true,
        $projectedFields = []
    );

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
    );

    public function scanCount(
        $filterExpression = '',
        array $fieldsMapping = [],
        array $paramsMapping = [],
        $indexName = true,
        $isConsistentRead = false,
        $parallel = 10
    );

}
