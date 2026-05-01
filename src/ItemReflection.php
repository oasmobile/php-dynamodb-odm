<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 14:50
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use ReflectionClass;
use ReflectionProperty;

use function array_keys;
use function is_null;

class ItemReflection
{
    protected string $itemClass;
    
    protected ?ReflectionClass $reflectionClass = null;
    protected ?Item $itemDefinition = null;
    /**
     * Maps each dynamodb attribute key to its corresponding class property name
     * @var array<string, string>
     */
    protected array $propertyMapping = [];
    /**
     * Maps each dynamodb attribute key to its type
     * @var array<string, string>
     */
    protected array $attributeTypes = [];
    /**
     * cas properties, in the format of property name => cas type
     * @var array<string, string>
     */
    protected array $casProperties = [];
    /**
     * partitioned hash keys, in the format of property name => partitioned hash key definition
     * @var PartitionedHashKey[]
     */
    protected array $partitionedHashKeys = [];
    /**
     * Maps class property name to its field definition
     * @var Field[]
     */
    protected array $fieldDefinitions = [];
    /**
     * Maps each class property name to its reflection property
     * @var ReflectionProperty[]
     */
    protected array $reflectionProperties = [];
    /**
     * Reserved attribute names will be cleared when hydrating an object
     * @var string[]
     */
    protected array $reservedAttributeNames;
    
    public function __construct(string $itemClass, array $reservedAttributeNames)
    {
        $this->itemClass              = $itemClass;
        $this->reservedAttributeNames = $reservedAttributeNames;
    }
    
    public function dehydrate(object $obj): array
    {
        if (!$obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object dehydrated is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }
        
        $array = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $value       = $this->getPropertyValue($obj, $propertyName);
            if (is_null($value) && $field->gsiKey) {
                continue;
            }
            $key         = $field->name ? : $propertyName;
            $array[$key] = $value;
        }
        
        return $array;
    }
    
    public function hydrate(array $array, ?object $obj = null): object
    {
        if ($obj === null) {
            $obj = $this->getReflectionClass()->newInstanceWithoutConstructor();
        }
        elseif (!$obj instanceof $this->itemClass) {
            throw new ODMException("You can not hydrate an object of wrong type, expected: " . $this->itemClass);
        }
        
        foreach ($array as $key => $value) {
            if (in_array($key, $this->reservedAttributeNames)) {
                // this attribute is reserved for other use
                continue;
            }
            if (!isset($this->propertyMapping[$key])) {
                // this property is not defined, skip it
                mwarning("Got an unknown attribute: %s with value %s", $key, print_r($value, true));
                continue;
            }
            $propertyName    = $this->propertyMapping[$key];
            $fieldDefinition = $this->fieldDefinitions[$propertyName];
            if ($fieldDefinition->type == "string") {
                // cast to string because dynamo stores "" as null
                $value = strval($value);
            }
            $this->updateProperty($obj, $propertyName, $value);
        }
        
        return $obj;
    }
    
    public function parse(): void
    {
        $this->reflectionClass = new ReflectionClass($this->itemClass);

        // Read class-level Attribute
        $itemAttrs = $this->reflectionClass->getAttributes(Item::class);
        if (empty($itemAttrs)) {
            throw new NotAnnotatedException("Class {$this->itemClass} is not configured as an Item");
        }
        $this->itemDefinition = $itemAttrs[0]->newInstance();

        // Read property-level Attributes
        $this->propertyMapping      = [];
        $this->fieldDefinitions     = [];
        $this->reflectionProperties = [];
        $this->attributeTypes       = [];
        $this->casProperties        = [];
        $this->partitionedHashKeys  = [];

        foreach ($this->reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isStatic()) {
                continue;
            }
            $propertyName = $reflectionProperty->getName();
            $this->reflectionProperties[$propertyName] = $reflectionProperty;

            $fieldAttrs = $reflectionProperty->getAttributes(Field::class);
            if (empty($fieldAttrs)) {
                continue;
            }
            /** @var Field $field */
            $field = $fieldAttrs[0]->newInstance();
            $fieldName = $field->name ?? $propertyName;
            $this->propertyMapping[$fieldName]     = $propertyName;
            $this->fieldDefinitions[$propertyName] = $field;
            $this->attributeTypes[$fieldName]      = $field->type;
            if ($field->cas !== Field::CAS_DISABLED) {
                $this->casProperties[$propertyName] = $field->cas;
            }

            $phkAttrs = $reflectionProperty->getAttributes(PartitionedHashKey::class);
            if (!empty($phkAttrs)) {
                $this->partitionedHashKeys[$propertyName] = $phkAttrs[0]->newInstance();
            }
        }
    }
    
    public function getAllPartitionedValues(string $hashKeyName, mixed $baseValue): array
    {
        if (!isset($this->partitionedHashKeys[$hashKeyName])) {
            return [$baseValue];
        }
        
        $def = $this->partitionedHashKeys[$hashKeyName];
        $ret = [];
        for ($i = 0; $i < $def->size; ++$i) {
            $ret[] = sprintf("%s-%s", $baseValue, dechex($i));
        }
        
        return $ret;
    }
    
    public function getPropertyValue(object $obj, string $propertyName): mixed
    {
        if (!$obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object accessed is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }
        
        if (!isset($this->reflectionProperties[$propertyName])) {
            throw new ODMException(
                "Object " . $this->itemClass . " doesn't have a property named: " . $propertyName
            );
        }
        $reflectionProperty = $this->reflectionProperties[$propertyName];
        
        return $reflectionProperty->getValue($obj);
    }
    
    public function updateProperty(object $obj, string $propertyName, mixed $value): void
    {
        if (!$obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object updated is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }
        
        if (!isset($this->reflectionProperties[$propertyName])) {
            throw new ODMException(
                "Object " . $this->itemClass . " doesn't have a property named: " . $propertyName
            );
        }
        $reflectionProperty = $this->reflectionProperties[$propertyName];
        $reflectionProperty->setValue($obj, $value);
    }
    
    public function getAttributeTypes(): array
    {
        return $this->attributeTypes;
    }
    
    public function getCasProperties(): array
    {
        return $this->casProperties;
    }
    
    /**
     * Returns field name (attribute key for dynamodb) according to property name
     */
    public function getFieldNameByPropertyName(string $propertyName): string
    {
        $field = $this->fieldDefinitions[$propertyName];
        
        return $field->name ? : $propertyName;
    }
    
    /**
     * @return array a map of property name to attribute key
     */
    public function getFieldNameMapping(): array
    {
        $ret = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $ret[$propertyName] = $field->name ? : $propertyName;
        }
        
        return $ret;
    }
    
    public function getProjectedAttributes(): array
    {
        if ($this->getItemDefinition()->projected) {
            return array_keys($this->propertyMapping);
        }
        else {
            return [];
        }
    }
    
    public function getItemClass(): string
    {
        return $this->itemClass;
    }
    
    public function getItemDefinition(): Item
    {
        return $this->itemDefinition;
    }
    
    /**
     * @return PartitionedHashKey[]
     */
    public function getPartitionedHashKeys(): array
    {
        return $this->partitionedHashKeys;
    }
    
    public function getPrimaryIdentifier(object|array $obj): string
    {
        $id = '';
        foreach ($this->getPrimaryKeys($obj) as $key => $value) {
            $id .= md5((string) $value);
        }
        
        return md5($id);
    }
    
    public function getPrimaryKeys(object|array $obj, bool $asAttributeKeys = true): array
    {
        $keys = [];
        foreach ($this->itemDefinition->primaryIndex->getKeys() as $key) {
            if (!isset($this->fieldDefinitions[$key])) {
                throw new AnnotationParsingException("Primary field " . $key . " is not defined.");
            }
            $attributeKey = $this->fieldDefinitions[$key]->name ? : $key;
            
            if (is_array($obj)) {
                if (!isset($obj[$attributeKey])) {
                    throw new ODMException(
                        "Cannot get identifier for incomplete object! <" . $attributeKey . "> is empty!"
                    );
                }
                $value = $obj[$attributeKey];
            }
            else {
                $value = $this->getPropertyValue($obj, $key);
            }
            
            if ($asAttributeKeys) {
                $keys[$attributeKey] = $value;
            }
            else {
                $keys[$key] = $value;
            }
        }
        
        return $keys;
    }
    
    public function getReflectionClass(): ReflectionClass
    {
        return $this->reflectionClass;
    }
    
    public function getRepositoryClass(): string
    {
        return $this->itemDefinition->repository ? : ItemRepository::class;
    }
    
    public function getTableName(): string
    {
        return $this->itemDefinition->table;
    }
}
