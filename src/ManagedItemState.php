<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 16:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

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
    
    public function getDirtyData()
    {
        if ($this->state != self::STATE_MANAGED) {
            return false;
        }
        
        $data = $this->itemReflection->dehydrate($this->item);
        if (array_diff_assoc($data, $this->originalData)
            || array_diff_assoc($this->originalData, $data)
        ) {
            return $data;
        }
        else {
            return false;
        }
    }
    
    public function setUpdated()
    {
        $this->originalData = $this->itemReflection->dehydrate($this->item);
    }
    
}
