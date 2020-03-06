<?php


namespace Oasis\Mlib\ODM\Dynamodb\DBAL;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\Connection;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;

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


    /**
     * @param $tableName
     * @param $dbConfig
     * @param $attributeTypes
     * @return Connection
     */
    public static function getConnection($tableName, $dbConfig, $attributeTypes)
    {
        if (!isset($dbConfig['driver'])) {
            $dbConfig['driver'] = self::DRIVER_DYNAMODB;
        }

        $cnnClassName = self::$driverMap[$dbConfig['driver']];

        return new $cnnClassName($tableName, $dbConfig, $attributeTypes);
    }
}
