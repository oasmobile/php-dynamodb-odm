<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-06
 * Time: 12:17
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\UnderlyingDatabaseException;

class ItemRepository
{
    /** @var  ItemManager */
    protected $itemManager;
    /** @var ItemReflection */
    protected $itemReflection;
    /** @var  DynamoDbTable */
    protected $dynamodbTable;
    
    /**
     * @var ManagedItemState[]
     * Maps object id to managed object
     */
    protected $itemManaged = [];
    
    public function __construct(ItemReflection $itemReflection, ItemManager $itemManager)
    {
        $this->itemManager    = $itemManager;
        $this->itemReflection = $itemReflection;
        
        // initialize table
        $tableName           = $itemManager->getDefaultTablePrefix() . $this->itemReflection->getTableName();
        $this->dynamodbTable = new DynamoDbTable(
            $itemManager->getDynamodbConfig(),
            $tableName,
            $this->itemReflection->getAttributeTypes(),
            $this->itemReflection->getCasField()
        );
    }
    
    public function query($conditions,
                          array $fields,
                          array $params,
                          $indexName = DynamoDbTable::PRIMARY_INDEX,
                          &$lastKey = null,
                          $evaluationLimit = 30,
                          $consistentRead = false)
    {
        $results = $this->dynamodbTable->query(
            $conditions,
            $fields,
            $params,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $consistentRead
        );
        $ret     = [];
        foreach ($results as $result) {
            $managed = $this->getManagedObject($result);
            $obj     = $this->itemReflection->hydrate($result, $managed);
            
            if (!$managed) {
                $this->persistFetchedItemData($obj, $result);
            }
            $ret[] = $obj;
        }
        
        return $ret;
    }
    
    public function queryAndRun(callable $callback,
                                $conditions = '',
                                array $fields = [],
                                array $params = [],
                                $indexName = DynamoDbTable::PRIMARY_INDEX,
                                $consistentRead = false)
    {
        $this->dynamodbTable->queryAndRun(
            function ($result) use ($callback) {
                $managed = $this->getManagedObject($result);
                $obj     = $this->itemReflection->hydrate($result, $managed);
                
                if (!$managed) {
                    $this->persistFetchedItemData($obj, $result);
                }
                
                return call_user_func($callback, $obj);
            },
            $conditions,
            $fields,
            $params,
            $indexName,
            $consistentRead
        );
    }
    
    public function scan($conditions = '',
                         array $fields = [],
                         array $params = [],
                         &$lastKey = null,
                         $evaluationLimit = 30)
    {
        $results = $this->dynamodbTable->scan(
            $conditions,
            $fields,
            $params,
            $lastKey,
            $evaluationLimit
        );
        $ret     = [];
        foreach ($results as $result) {
            $managed = $this->getManagedObject($result);
            $obj     = $this->itemReflection->hydrate($result, $managed);
            
            if (!$managed) {
                $this->persistFetchedItemData($obj, $result);
            }
            $ret[] = $obj;
        }
        
        return $ret;
    }
    
    public function scanAndRun(callable $callback,
                               $conditions = '',
                               array $fields = [],
                               array $params = [])
    {
        $this->dynamodbTable->scanAndRun(
            function ($result) use ($callback) {
                $managed = $this->getManagedObject($result);
                $obj     = $this->itemReflection->hydrate($result, $managed);
                
                if (!$managed) {
                    $this->persistFetchedItemData($obj, $result);
                }
                
                return call_user_func($callback, $obj);
            },
            $conditions,
            $fields,
            $params
        );
    }
    
    public function get($keys, $isConsistentRead = false)
    {
        $result = $this->dynamodbTable->get($keys, $isConsistentRead);
        if (is_array($result)) {
            $managed = $this->getManagedObject($result);
            $obj     = $this->itemReflection->hydrate($result, $managed);
            
            if (!$managed) {
                $this->persistFetchedItemData($obj, $result);
            }
            
            return $obj;
        }
        elseif ($result === null) {
            return null;
        }
        else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb is not an array!");
        }
    }
    
    public function persist($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException("Persisting wrong boject, expecting: " . $this->itemReflection->getItemClass());
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (isset($this->itemManaged[$id])) {
            throw new ODMException("Persisting existing object: " . print_r($obj, true));
        }
        
        $managedState = new ManagedItemState($this->itemReflection, $obj);
        $managedState->setState(ManagedItemState::STATE_NEW);
        $this->itemManaged[$id] = $managedState;
    }
    
    public function flush()
    {
        foreach ($this->itemManaged as $managedItemState) {
            if ($managedItemState->isRemoved()) {
                $this->dynamodbTable->delete(
                    $this->itemReflection->getPrimaryKeys($managedItemState->getItem())
                );
            }
            elseif ($managedItemState->isNew()) {
                $this->dynamodbTable->set(
                    $this->itemReflection->dehydrate($managedItemState->getItem())
                );
            }
            else {
                $dirtyData = $managedItemState->getDirtyData();
                if ($dirtyData) {
                    $this->dynamodbTable->set($dirtyData);
                }
            }
        }
    }
    
    protected function getManagedObject($resultData)
    {
        $id = $this->itemReflection->getPrimaryIdentifier($resultData);
        if (isset($this->itemManaged[$id])) {
            return $this->itemManaged[$id]->getItem();
        }
        else {
            return null;
        }
    }
    
    protected function persistFetchedItemData($obj, array $originalData)
    {
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            $this->itemManaged[$id] = new ManagedItemState($this->itemReflection, $obj, $originalData);
        }
        else {
            if ($this->itemManaged[$id]->isNew()) {
                throw new ODMException("Newly created item conflicts with fetched remote data: " . print_r($obj, true));
            }
            $this->itemManaged[$id]->setItem($obj);
            $this->itemManaged[$id]->setOriginalData($originalData);
        }
    }
    
    public function remove($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object removed is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }
        
        $this->itemManaged[$id]->setState(ManagedItemState::STATE_REMOVED);
    }
    
    public function detach($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object detached is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }
        
        unset($this->itemManaged[$id]);
    }
    
    public function refresh($obj)
    {
        if (!$this->itemReflection->getReflectionClass()->isInstance($obj)) {
            throw new ODMException(
                "Object detached is not of correct type, expected: " . $this->itemReflection->getItemClass()
            );
        }
        $id = $this->itemReflection->getPrimaryIdentifier($obj);
        if (!isset($this->itemManaged[$id])) {
            throw new ODMException("Object is not managed: " . print_r($obj, true));
        }
        
        $this->get($this->itemReflection->getPrimaryKeys($obj), true);
    }
}
