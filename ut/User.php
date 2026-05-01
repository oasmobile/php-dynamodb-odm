<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 17:08
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;

#[Item(
    table: 'users',
    primaryIndex: new Index(hash: 'id'),
    globalSecondaryIndices: [
        new Index(hash: 'hometown', range: 'age'),
        new Index(hash: 'hometown', range: 'wage'),
        new Index(hash: 'hometownPartition', range: 'age', name: 'home-age-gsi'),
    ],
)]
class User
{
    #[Field(type: 'number', name: 'uid')]
    protected $id = 0;

    #[Field(type: 'string', cas: Field::CAS_ENABLED)]
    protected $name;

    #[Field(type: 'string')]
    protected $alias = '';

    #[Field(type: 'number')]
    protected $age;

    #[Field(type: 'number', name: 'salary')]
    protected $wage;

    #[Field(type: 'string')]
    protected $hometown = 'new york';

    #[Field()]
    #[PartitionedHashKey(hashField: 'id', baseField: 'hometown')]
    protected $hometownPartition;

    #[Field(type: 'number', name: 'ts', cas: Field::CAS_TIMESTAMP)]
    protected $lastUpdated;

    #[Field(type: 'string')]
    protected $dummy;
    
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
