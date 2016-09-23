<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-06
 * Time: 12:17
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException;
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
    
    public function flush()
    {
        $removed = [];
        foreach ($this->itemManaged as $oid => $managedItemState) {
            $item = $managedItemState->getItem();
            if ($managedItemState->isRemoved()) {
                $this->dynamodbTable->delete(
                    $this->itemReflection->getPrimaryKeys($item)
                );
                $removed[] = $oid;
            }
            elseif ($managedItemState->isNew()) {
                $ret = $this->dynamodbTable->set(
                    $this->itemReflection->dehydrate($item),
                    true,
                    $updatedCasValue
                );
                if (!$ret) {
                    throw new DataConsistencyException(
                        "Item exists! type = " . $this->itemReflection->getItemClass()
                    );
                }
                $casProperty = $this->itemReflection->getCasPropertyName();
                if ($casProperty) {
                    $this->itemReflection->updateProperty($item, $casProperty, $updatedCasValue);
                }
                $managedItemState->setState(ManagedItemState::STATE_MANAGED);
                $managedItemState->setUpdated();
            }
            else {
                $dirtyData = $managedItemState->getDirtyData();
                if ($dirtyData) {
                    $ret = $this->dynamodbTable->set(
                        $dirtyData,
                        true,
                        $updatedCasValue
                    );
                    if (!$ret) {
                        throw new DataConsistencyException(
                            "Item upated elsewhere! type = " . $this->itemReflection->getItemClass()
                        );
                    }
                    $casProperty = $this->itemReflection->getCasPropertyName();
                    if ($casProperty) {
                        $this->itemReflection->updateProperty($item, $casProperty, $updatedCasValue);
                    }
                    
                    $managedItemState->setUpdated();
                }
            }
        }
        foreach ($removed as $id) {
            unset($this->itemManaged[$id]);
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
    
    public function query($conditions,
                          array $params,
                          $indexName = DynamoDbTable::PRIMARY_INDEX,
                          &$lastKey = null,
                          $evaluationLimit = 30,
                          $consistentRead = false)
    {
        $fields  = $this->getFieldsArray($conditions);
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
                                array $params = [],
                                $indexName = DynamoDbTable::PRIMARY_INDEX,
                                $consistentRead = false)
    {
        $fields = $this->getFieldsArray($conditions);
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
        
        $this->get($this->itemReflection->getPrimaryKeys($obj, false), true);
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
    
    public function scan($conditions = '',
                         array $params = [],
                         &$lastKey = null,
                         $evaluationLimit = 30)
    {
        $fields  = $this->getFieldsArray($conditions);
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
                               array $params = [])
    {
        $fields = $this->getFieldsArray($conditions);
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
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
        $translatedKeys   = [];
        foreach ($keys as $k => $v) {
            if (!isset($fieldNameMapping[$k])) {
                throw new ODMException("Cannot find primary index field: $k!");
            }
            $k                  = $fieldNameMapping[$k];
            $translatedKeys[$k] = $v;
        }
        $result = $this->dynamodbTable->get($translatedKeys, $isConsistentRead);
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
    
    protected function getFieldsArray($conditions)
    {
        $ret = preg_match_all('/#(?P<field>[a-zA-Z_][a-zA-Z0-9_]*)/', $conditions, $matches);
        if (!$ret) {
            return [];
        }
        
        $result = [];
        if (isset($matches['field']) && is_array($matches['field'])) {
            $fieldNameMapping = $this->itemReflection->getFieldNameMapping();
            
            foreach ($matches['field'] as $fieldName) {
                if (!isset($fieldNameMapping[$fieldName])) {
                    throw new ODMException("Cannot find field named $fieldName!");
                }
                $result["#" . $fieldName] = $fieldNameMapping[$fieldName];
            }
        }
        
        return $result;
    }
}
