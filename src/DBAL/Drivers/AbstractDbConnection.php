<?php


namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers;

use Oasis\Mlib\ODM\Dynamodb\ItemReflection;

/**
 * Class AbstractDbConnection
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers
 */
abstract class AbstractDbConnection implements Connection
{
    protected array $dbConfig;

    protected string $tableName = '';

    protected array $attributeTypes = [];

    protected ?ItemReflection $itemReflection = null;

    /**
     * AbstractDbConnection constructor.
     * @param $dbConfig
     */
    public function __construct($dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public function getDatabaseConfig(): array
    {
        return $this->dbConfig;
    }

    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    public function setAttributeTypes(array $attributeTypes): void
    {
        $this->attributeTypes = $attributeTypes;
    }

    public function setItemReflection(ItemReflection $itemReflection): void
    {
        $this->itemReflection = $itemReflection;
    }

}
