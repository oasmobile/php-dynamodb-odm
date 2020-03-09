<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-06
 * Time: 12:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\Connection;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchema;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\DynamoDbSchema;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Class DriverManager
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL
 */
class DriverManager
{
    public const DRIVER_DYNAMODB = 'dynamodb';

    private static $driverMap = [
        self::DRIVER_DYNAMODB => DynamoDbConnection::class,
    ];

    private static $schemaMap = [
        self::DRIVER_DYNAMODB => DynamoDbSchema::class,
    ];


    /**
     * @param $tableName
     * @param $dbConfig
     * @param $attributeTypes
     * @return Connection
     */
    public static function getConnection($tableName, $dbConfig, $attributeTypes)
    {
        $driverName   = self::getDriverName($dbConfig);
        $cnnClassName = self::$driverMap[$driverName];

        return new $cnnClassName($tableName, $dbConfig, $attributeTypes);
    }

    /**
     * @param  ItemManager  $im
     * @param  array  $classReflections
     * @param  callable  $outputFunction
     * @return AbstractSchema
     */
    public static function getSchema(ItemManager $im, $classReflections, callable $outputFunction = null)
    {
        $driverName      = self::getDriverName($im->getDatabaseConfig());
        $schemaClassName = self::$schemaMap[$driverName];

        return new $schemaClassName($im, $classReflections, $outputFunction);
    }

    private static function getDriverName($dbConfig)
    {
        if (isset($dbConfig['driver']) && strlen($dbConfig['driver']) > 0) {
            return $dbConfig['driver'];
        }
        else {
            return self::DRIVER_DYNAMODB;
        }
    }
}
