<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 16:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

use function is_null;
use function is_string;

class ManagedItemState
{
    public const STATE_NEW     = 1;
    public const STATE_MANAGED = 2;
    public const STATE_REMOVED = 3;
    
    protected int $state = self::STATE_MANAGED;
    
    public function __construct(
        protected ItemReflection $itemReflection,
        protected object $item,
        protected array $originalData = [],
    ) {
    }
    
    public function hasDirtyData(): bool
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
    
    public function isNew(): bool
    {
        return $this->state == self::STATE_NEW;
    }
    
    public function isRemoved(): bool
    {
        return $this->state == self::STATE_REMOVED;
    }
    
    public function updatePartitionedHashKeys(?callable $hashFunction = null): void
    {
        foreach ($this->itemReflection->getPartitionedHashKeys() as $partitionedHashKey => $def) {
            $baseValue  = $this->itemReflection->getPropertyValue($this->item, $def->baseField);
            $hashSource = $this->itemReflection->getPropertyValue($this->item, $def->hashField);
            if (is_callable($hashFunction)) {
                $hashSource = call_user_func($hashFunction, $hashSource);
            }
            $hashNumber    = hexdec(substr(md5((string) $hashSource), 0, 8));
            $hashRemainder = dechex($hashNumber % $def->size);
            $hashResult    = sprintf("%s-%s", $baseValue, $hashRemainder);
            $this->itemReflection->updateProperty($this->item, $partitionedHashKey, $hashResult);
        }
    }
    
    public function updateCASTimestamps(int $timestampOffset = 0): void
    {
        $now = time() + $timestampOffset;
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            if ($casType == Field::CAS_TIMESTAMP) {
                $this->itemReflection->updateProperty($this->item, $propertyName, $now);
            }
        }
    }
    
    public function getCheckConditionData(): array
    {
        $checkValues = [];
        foreach ($this->itemReflection->getCasProperties() as $propertyName => $casType) {
            $fieldName               = $this->itemReflection->getFieldNameByPropertyName($propertyName);
            $checkValues[$fieldName] = $this->originalData[$fieldName] ?? null;
        }
        
        return $checkValues;
    }
    
    public function getItem(): object
    {
        return $this->item;
    }
    
    public function setItem(object $item): void
    {
        $this->item = $item;
    }
    
    public function getOriginalData(): array
    {
        return $this->originalData;
    }
    
    public function setOriginalData(array $originalData): void
    {
        $this->originalData = $originalData;
    }
    
    public function getOriginalValue(string $key): mixed
    {
        return $this->originalData[$key] ?? null;
    }
    
    public function setState(int $state): void
    {
        $this->state = $state;
    }
    
    public function setUpdated(): void
    {
        $this->originalData = $this->itemReflection->dehydrate($this->item);
    }

    protected function isDataEqual(mixed &$a, mixed &$b): bool
    {
        // empty string is considered null in dynamodb
        if (
            (is_null($a) && is_string($b) && $b === '')
            || (is_null($b) && is_string($a) && $a === '')
        ) {
            return true;
        }
        
        if (gettype($a) != gettype($b)) {
            return false;
        }
        
        return match (true) {
            is_double($a) => "$a" == "$b",
            is_array($a) => $this->isArrayDataEqual($a, $b),
            is_resource($a), is_object($a) => throw new ODMException("DynamoDb data cannot contain value of resource/object"),
            default => $a === $b,
        };
    }

    private function isArrayDataEqual(array &$a, array &$b): bool
    {
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
    }
}
