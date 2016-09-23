<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:08
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\CASTimestamp;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;

/**
 * Class User
 *
 * @Item(
 *     table="users",
 *     primaryIndex=@Index(hash="id"),
 *     globalSecondaryIndices={
 *     {"hometown", "age"},
 *     {"hometown", "wage"}
 *     }
 *     )
 * @package Oasis\Mlib\ODM\Dynamodb
 */
class User
{
    /**
     * @var int
     * @Field(type="number", name="uid")
     */
    protected $id = 0;
    /**
     * @var
     * @Field(type="string")
     */
    protected $name;
    /**
     * @var
     * @Field(type="string")
     */
    protected $alias = '';
    /**
     * @var
     * @Field(type="number")
     */
    protected $age;
    /**
     * @var
     * @Field(type="number", name="salary")
     */
    protected $wage;
    /**
     * @var
     * @Field(type="string")
     */
    protected $hometown = 'new york';
    /**
     * @var
     * @CASTimestamp()
     * @Field(type="number", name="ts")
     */
    protected $lastUpdated;
    
    public $haha;
    
    /**
     * @return mixed
     */
    public function getAge()
    {
        return $this->age;
    }
    
    /**
     * @param mixed $age
     */
    public function setAge($age)
    {
        $this->age = $age;
    }
    
    /**
     * @return mixed
     */
    public function getAlias()
    {
        return $this->alias;
    }
    
    /**
     * @param mixed $alias
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;
    }
    
    /**
     * @return mixed
     */
    public function getHometown()
    {
        return $this->hometown;
    }
    
    /**
     * @param mixed $hometown
     */
    public function setHometown($hometown)
    {
        $this->hometown = $hometown;
    }
    
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }
    
    /**
     * @return mixed
     */
    public function getLastUpdated()
    {
        return $this->lastUpdated;
    }
    
    /**
     * @param mixed $lastUpdated
     */
    public function setLastUpdated($lastUpdated)
    {
        $this->lastUpdated = $lastUpdated;
    }
    
    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }
    
    /**
     * @return mixed
     */
    public function getWage()
    {
        return $this->wage;
    }
    
    /**
     * @param mixed $wage
     */
    public function setWage($wage)
    {
        $this->wage = $wage;
    }
}
