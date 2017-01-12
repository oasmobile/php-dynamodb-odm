<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-06
 * Time: 12:17
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
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
            $this->itemReflection->getAttributeTypes()
        );
    }
    
    public function batchGet($groupOfKeys, $isConsistentRead = false)
    {
        /** @var string[] $fieldNameMapping */
        $fieldNameMapping      = $this->itemReflection->getFieldNameMapping();
        $groupOfTranslatedKeys = [];
        foreach ($groupOfKeys as $keys) {
            $translatedKeys = [];
            foreach ($keys as $k => $v) {
                if (!isset($fieldNameMapping[$k])) {
                    throw new ODMException("Cannot find primary index field: $k!");
                }
                $k                  = $fieldNameMapping[$k];
                $translatedKeys[$k] = $v;
            }
            $groupOfTranslatedKeys[] = $translatedKeys;
        }
        $resultSet = $this->dynamodbTable->batchGet($groupOfTranslatedKeys, $isConsistentRead);
        if (is_array($resultSet)) {
            $ret = [];
            foreach ($resultSet as $singleResult) {
                $managed = $this->getManagedObject($singleResult);
                $obj     = $this->itemReflection->hydrate($singleResult, $managed);
                
                if (!$managed) {
                    $this->persistFetchedItemData($obj, $singleResult);
                }
                
                $ret[] = $obj;
            }
            
            return $ret;
        }
        else {
            throw new UnderlyingDatabaseException("Result returned from dynamodb for BatchGet() is not an array!");
        }
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
                $managedItemState->updateCASTimestamps();
                $ret = $this->dynamodbTable->set(
                    $this->itemReflection->dehydrate($item),
                    $managedItemState->getCheckConditionData()
                );
                if ($ret === false) {
                    throw new DataConsistencyException(
                        "Item exists! type = " . $this->itemReflection->getItemClass()
                    );
                }
                $managedItemState->setState(ManagedItemState::STATE_MANAGED);
                $managedItemState->setUpdated();
            }
            else {
                $hasData = $managedItemState->hasDirtyData();
                if ($hasData) {
                    $managedItemState->updateCASTimestamps();
                    $ret = $this->dynamodbTable->set(
                        $this->itemReflection->dehydrate($item),
                        $managedItemState->getCheckConditionData()
                    );
                    if (!$ret) {
                        throw new DataConsistencyException(
                            "Item upated elsewhere! type = " . $this->itemReflection->getItemClass()
                        );
                    }
                    $managedItemState->setUpdated();
                }
            }
        }
        foreach ($removed as $id) {
            unset($this->itemManaged[$id]);
        }
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
    
    public function parallelScanAndRun($parallel,
                                       callable $callback,
                                       $conditions = '',
                                       array $params = [],
                                       $indexName = DynamoDbIndex::PRIMARY_INDEX,
                                       $isConsistentRead = false,
                                       $isAscendingOrder = true
    )
    {
        $fields = $this->getFieldsArray($conditions);
        $this->dynamodbTable->parallelScanAndRun(
            $parallel,
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
            $isConsistentRead,
            $isAscendingOrder
        );
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
                          $indexName = DynamoDbIndex::PRIMARY_INDEX,
                          $filterExpression = '',
                          &$lastKey = null,
                          $evaluationLimit = 30,
                          $isConsistentRead = false,
                          $isAscendingOrder = true)
    {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
        $results = $this->dynamodbTable->query(
            $conditions,
            $fields,
            $params,
            $indexName,
            $filterExpression,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder
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
                                $indexName = DynamoDbIndex::PRIMARY_INDEX,
                                $filterExpression = '',
                                $isConsistentRead = false,
                                $isAscendingOrder = true)
    {
        $fields = array_merge($this->getFieldsArray($conditions), $this->getFieldsArray($filterExpression));
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
            $filterExpression,
            $isConsistentRead,
            $isAscendingOrder
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
                         $indexName = DynamoDbIndex::PRIMARY_INDEX,
                         &$lastKey = null,
                         $evaluationLimit = 30,
                         $isConsistentRead = false,
                         $isAscendingOrder = true)
    {
        $fields  = $this->getFieldsArray($conditions);
        $results = $this->dynamodbTable->scan(
            $conditions,
            $fields,
            $params,
            $indexName,
            $lastKey,
            $evaluationLimit,
            $isConsistentRead,
            $isAscendingOrder
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
                               array $params = [],
                               $indexName = DynamoDbIndex::PRIMARY_INDEX,
                               $isConsistentRead = false,
                               $isAscendingOrder = true
    )
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
            $params,
            $indexName,
            $isConsistentRead,
            $isAscendingOrder
        );
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
}
