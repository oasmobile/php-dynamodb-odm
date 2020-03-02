<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 16:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

class ManagedItemState
{
    const STATE_NEW     = 1;
    const STATE_MANAGED = 2;
    const STATE_REMOVED = 3;
    
    /** @var  ItemReflection */
    protected $itemReflection;
    protected $item;
    /**
     * @var array
     */
    protected $originalData;
    protected $state = self::STATE_MANAGED;
    
    public function __construct(ItemReflection $itemReflection, $item, array $originalData = [])
    {
        $this->itemReflection = $itemReflection;
        $this->item           = $item;
        $this->originalData   = $originalData;
    }
    
    public function hasDirtyData()
    {
        if ($this->state != self::STATE_MANAGED) {
            return false;
        }
        
        $data = $this->itemReflection->dehydrate($this->item);
        if (!$this->isDataEqual($data, $this->originalData)) {
            return true;
        }
        else {
            return false;
        }
    }
    
    /**
     * @return boolean
     */
    public function isNew()
    {
        return $this->state == self::STATE_NEW;
    }
    
    /**
     * @return boolean
     */
    public function isRemoved()
    {
        return $this->state == self::STATE_REMOVED;
    }
    
    public function updatePartitionedHashKeys($hashFunction = null)
    {
        foreach ($this->itemReflection->getPartitionedHashKeys() as $partitionedHashKey => $def) {
            $baseValue  = $this->itemReflection->getPropertyValue($this->item, $def->baseField);
            $hashSource = $this->itemReflection->getPropertyValue($this->item, $def->hashField);
            if (is_callable($hashFunction)) {
                $hashSource = call_user_func($hashFunction, $hashSource);
            }
            $hashNumber    = hexdec(substr(md5($hashSource), 0, 8));
            $hashRemainder = dechex($hashNumber % $def->size);
            $hashResult    = sprintf("%s-%s", $baseValue, $hashRemainder);
            $this->itemReflection->updateProperty($this->item, $partitionedHashKey, $hashResult);
        }
    }
    
    public function updateCASTimestamps($timestampOffset = 0)
    {
        $now = time() + $timestampOffset;
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            if ($casType == Field::CAS_TIMESTAMP) {
                $this->itemReflection->updateProperty($this->item, $propertyName, $now);
            }
        }
    }
    
    /**
     * @return array
     */
    public function getCheckConditionData()
    {
        $checkValues = [];
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            $fieldName               = $this->itemReflection->getFieldNameByPropertyName($propertyName);
            $checkValues[$fieldName] = isset($this->originalData[$fieldName]) ? $this->originalData[$fieldName] : null;
        }
        
        return $checkValues;
    }
    
    /**
     * @return mixed
     */
    public function getItem()
    {
        return $this->item;
    }
    
    /**
     * @param mixed $item
     */
    public function setItem($item)
    {
        $this->item = $item;
    }
    
    /**
     * @return array
     */
    public function getOriginalData()
    {
        return $this->originalData;
    }
    
    /**
     * @param array $originalData
     */
    public function setOriginalData($originalData)
    {
        $this->originalData = $originalData;
    }
    
    public function getOriginalValue($key)
    {
        if (isset($this->originalData[$key])) {
            return $this->originalData[$key];
        }
        else {
            return null;
        }
    }
    
    /**
     * @param int $state
     */
    public function setState($state)
    {
        $this->state = $state;
    }
    
    public function setUpdated()
    {
        $this->originalData = $this->itemReflection->dehydrate($this->item);
    }
    
    protected function isDataEqual(&$a, &$b)
    {
        // empty string is considered null in dynamodb
        if (
            (\is_null($a) && \is_string($b) && $b === '')
            || (\is_null($b) && \is_string($a) && $a === '')
        ) {
            return true;
        }
        
        if (gettype($a) != gettype($b)) {
            return false;
        }
        
        switch (true) {
            case (is_double($a)):
                return "$a" == "$b";
                break;
            case (is_array($a)):
                if (count($a) !== count($b)) {
                    return false;
                }
                foreach ($a as $k => &$v) {
                    if (!key_exists($k, $b)) {
                        return false;
                    }
                    if (!$this->isDataEqual($v, $b[$k])) {
                        return false;
                    }
                }
                
                // every $k in $a can be found in $b and is equal
                return true;
                break;
            case (is_resource($a)):
            case (is_object($a)):
                throw new ODMException("DynamoDb data cannot contain value of resource/object");
                break;
            default:
                return $a === $b;
        }
    }
}
