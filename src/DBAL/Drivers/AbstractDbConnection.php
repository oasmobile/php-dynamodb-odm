<?php


namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;

/**
 * Class AbstractDbConnection
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
abstract class AbstractDbConnection implements Connection
{
    /**
     * @var array
     */
    protected $dbConfig;

    /**
     * @var string
     */
    protected $tableName = '';

    /**
     * @var array
     */
    protected $attributeTypes = [];

    /**
     * AbstractDbConnection constructor.
     * @param $dbConfig
     */
    public function __construct($dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public function getDatabaseConfig()
    {
        return $this->dbConfig;
    }

    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
    }

    public function setAttributeTypes($attributeTypes)
    {
        $this->attributeTypes = $attributeTypes;
    }

}
