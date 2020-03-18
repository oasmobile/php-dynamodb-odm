<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 14:50
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;
use Oasis\Mlib\ODM\Dynamodb\Annotations\PartitionedHashKey;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

class ItemReflection
{
    protected $itemClass;
    
    /** @var  \ReflectionClass */
    protected $reflectionClass;
    /** @var  Item */
    protected $itemDefinition;
    /**
     * @var  array
     * Maps each dynamodb attribute key to its corresponding class property name
     */
    protected $propertyMapping;
    /**
     * @var array
     * Maps each dynamodb attribute key to its type
     */
    protected $attributeTypes;
    /**
     * @var array
     * cas properties, in the format of property name => cas type
     */
    protected $casProperties;
    /**
     * @var PartitionedHashKey[]
     * partitioned hash keys, in the format of property name => partioned hash key definition
     */
    protected $partitionedHashKeys;
    /**
     * @var  Field[]
     * Maps class property name to its field definition
     */
    protected $fieldDefinitions;
    /**
     * @var \ReflectionProperty[]
     * Maps each class property name to its reflection property
     */
    protected $reflectionProperties;
    /**
     * @var array
     * Reserved attribute names will be cleared when hydrating an object
     */
    protected $reservedAttributeNames;
    
    public function __construct($itemClass, $reservedAttributeNames)
    {
        $this->itemClass              = $itemClass;
        $this->reservedAttributeNames = $reservedAttributeNames;
    }
    
    public function dehydrate($obj)
    {
        if (!is_object($obj)) {
            throw new ODMException("You may only dehydrate an object!");
        }
        
        if (!$obj instanceof $this->itemClass) {
            throw new ODMException(
                "Object dehydrated is not of correct type, expected: " . $this->itemClass . ", got: " . get_class($obj)
            );
        }
        
        $array = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $value       = $this->getPropertyValue($obj, $propertyName);
            if (\is_null($value) && $field->gsiKey) {
                continue;
            }
            $key         = $field->name ? : $propertyName;
            $array[$key] = $value;
        }
        
        return $array;
    }
    
    public function hydrate(array $array, $obj = null)
    {
        if ($obj === null) {
            $obj = $this->getReflectionClass()->newInstanceWithoutConstructor();
        }
        elseif (!is_object($obj) || !$obj instanceof $this->itemClass) {
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
            } else if($fieldDefinition->type == 'object') {
                $value = $value ? unserialize(($value)) : null;
            }
            $this->updateProperty($obj, $propertyName, $value);
        }
        
        return $obj;
    }
    
    public function parse(Reader $reader)
    {
        // initialize class annotation info
        $this->reflectionClass = new \ReflectionClass($this->itemClass);
        $this->itemDefinition  = $reader->getClassAnnotation($this->reflectionClass, Item::class);
        if (!$this->itemDefinition) {
            throw new NotAnnotatedException("Class " . $this->itemClass . " is not configured as an Item");
        }
        
        // initialize property annotation info
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
            $propertyName                              = $reflectionProperty->getName();
            $this->reflectionProperties[$propertyName] = $reflectionProperty;
            
            /** @var Field $field */
            $field = $reader->getPropertyAnnotation($reflectionProperty, Field::class);
            if (!$field) {
                continue;
            }
            $fieldName                             = $field->name ? : $propertyName;
            $this->propertyMapping[$fieldName]     = $propertyName;
            $this->fieldDefinitions[$propertyName] = $field;
            $this->attributeTypes[$fieldName]      = $field->type;
            if ($field->cas != Field::CAS_DISABLED) {
                $this->casProperties[$propertyName] = $field->cas;
            }
            
            /** @var PartitionedHashKey $partitionedHashKeyDef */
            $partitionedHashKeyDef = $reader->getPropertyAnnotation($reflectionProperty, PartitionedHashKey::class);
            if ($partitionedHashKeyDef) {
                $this->partitionedHashKeys[$propertyName] = $partitionedHashKeyDef;
            }
        }
    }
    
    public function getAllPartitionedValues($hashKeyName, $baseValue)
    {
        if (!isset($this->partitionedHashKeys[$hashKeyName])) {
            // mdebug("The field %s is not declared as a PartitionedHashKey!", $hashKeyName)
            return [$baseValue];
        }
        
        $def = $this->partitionedHashKeys[$hashKeyName];
        $ret = [];
        for ($i = 0; $i < $def->size; ++$i) {
            $ret[] = sprintf("%s-%s", $baseValue, dechex($i));
        }
        
        return $ret;
    }
    
    public function getPropertyValue($obj, $propertyName)
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
        $relfectionProperty = $this->reflectionProperties[$propertyName];
        $oldAccessibility   = $relfectionProperty->isPublic();
        $relfectionProperty->setAccessible(true);
        $ret = $relfectionProperty->getValue($obj);
        $relfectionProperty->setAccessible($oldAccessibility);
        
        return $ret;
    }
    
    public function updateProperty($obj, $propertyName, $value)
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
        $relfectionProperty = $this->reflectionProperties[$propertyName];
        $oldAccessibility   = $relfectionProperty->isPublic();
        $relfectionProperty->setAccessible(true);
        $relfectionProperty->setValue($obj, $value);
        $relfectionProperty->setAccessible($oldAccessibility);
    }
    
    /**
     * @return mixed
     */
    public function getAttributeTypes()
    {
        return $this->attributeTypes;
    }
    
    /**
     * @return array
     */
    public function getCasProperties()
    {
        return $this->casProperties;
    }
    
    /**
     * Returns field name (attribute key for dynamodb) according to property name
     *
     * @param $propertyName
     *
     * @return string
     */
    public function getFieldNameByPropertyName($propertyName)
    {
        $field = $this->fieldDefinitions[$propertyName];
        
        return $field->name ? : $propertyName;
    }
    
    /**
     * @return array a map of property name to attribute key
     */
    public function getFieldNameMapping()
    {
        $ret = [];
        foreach ($this->fieldDefinitions as $propertyName => $field) {
            $ret[$propertyName] = $field->name ? : $propertyName;
        }
        
        return $ret;
    }
    
    public function getProjectedAttributes()
    {
        if ($this->getItemDefinition()->projected) {
            return \array_keys($this->propertyMapping);
        }
        else {
            return [];
        }
    }
    
    /**
     * @return mixed
     */
    public function getItemClass()
    {
        return $this->itemClass;
    }
    
    /**
     * @return Item
     */
    public function getItemDefinition()
    {
        return $this->itemDefinition;
    }
    
    /**
     * @return PartitionedHashKey[]
     */
    public function getPartitionedHashKeys()
    {
        return $this->partitionedHashKeys;
    }
    
    public function getPrimaryIdentifier($obj)
    {
        $id = '';
        foreach ($this->getPrimaryKeys($obj) as $key => $value) {
            $id .= md5($value);
        }
        
        return md5($id);
    }
    
    public function getPrimaryKeys($obj, $asAttributeKeys = true)
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
    
    /**
     * @return \ReflectionClass
     */
    public function getReflectionClass()
    {
        return $this->reflectionClass;
    }
    
    public function getRepositoryClass()
    {
        return $this->itemDefinition->repository ? : ItemRepository::class;
    }
    
    public function getTableName()
    {
        return $this->itemDefinition->table;
    }
}
