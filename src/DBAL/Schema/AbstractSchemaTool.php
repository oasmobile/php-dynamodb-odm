<?php
/**
 * Created by PhpStorm.
 * User: xuchang
 * Date: 2020-03-09
 * Time: 12:00
 */

namespace Oasis\Mlib\ODM\Dynamodb\DBAL\Schema;

use Oasis\Mlib\ODM\Dynamodb\ItemManager;

/**
 * Class AbstractSchema
 * @package Oasis\Mlib\ODM\Dynamodb\DBAL\Schema
 */
abstract class AbstractSchemaTool
{
    /**
     * @var ItemManager
     */
    protected $itemManager;

    /**
     * @var array
     */
    protected $classReflections;

    /**
     * @var callable
     */
    protected $outputFunction;

    public function __construct(ItemManager $im, $classReflections, callable $outputFunction = null)
    {
        $this->itemManager      = $im;
        $this->classReflections = $classReflections;
        $this->outputFunction   = $outputFunction;
    }

    abstract public function createSchema($skipExisting, $dryRun);

    abstract public function updateSchema($isDryRun);

    abstract public function dropSchema();

    /**
     * @return ItemManager
     */
    protected function getItemManager()
    {
        return $this->itemManager;
    }

    protected function getManagedItemClasses()
    {
        return $this->classReflections;
    }

    protected function outputWrite($message)
    {
        if (is_callable($this->outputFunction)) {
            $output = $this->outputFunction;
            $output($message);
        }
        else {
            mnotice($message);
        }
    }


}
