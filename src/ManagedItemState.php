<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 16:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;

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
        if (array_diff_assoc($data, $this->originalData)
            || array_diff_assoc($this->originalData, $data)
        ) {
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
    
}
