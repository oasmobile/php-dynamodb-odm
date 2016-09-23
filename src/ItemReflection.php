<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 14:50
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Doctrine\Common\Annotations\Reader;
use Oasis\Mlib\ODM\Dynamodb\Annotations\CASTimestamp;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;
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
    /** @var string CAS property name */
    protected $casPropertyName;
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
    
    public function __construct($itemClass)
    {
        $this->itemClass = $itemClass;
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
            $relfectionProperty = $this->reflectionProperties[$propertyName];
            $oldAccessibility   = $relfectionProperty->isPublic();
            $relfectionProperty->setAccessible(true);
            $value = $relfectionProperty->getValue($obj);
            $relfectionProperty->setAccessible($oldAccessibility);
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
            $relfectionProperty = $this->reflectionProperties[$propertyName];
            $oldAccessibility   = $relfectionProperty->isPublic();
            $relfectionProperty->setAccessible(true);
            $relfectionProperty->setValue($obj, $value);
            $relfectionProperty->setAccessible($oldAccessibility);
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
        $this->casPropertyName      = '';
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
            if ($reader->getPropertyAnnotation($reflectionProperty, CASTimestamp::class)) {
                if ($this->casPropertyName) {
                    throw new AnnotationParsingException(
                        "Duplicate CASTimestamp field: " . $this->casPropertyName . ", " . $fieldName
                    );
                }
                $this->casPropertyName = $propertyName;
            }
        }
    }
    
    /**
     * @return mixed
     */
    public function getAttributeTypes()
    {
        return $this->attributeTypes;
    }
    
    /**
     * @return array a map of field name to attribute key
     */
    public function getFieldNameMapping()
    {
        $ret = [];
        foreach ($this->fieldDefinitions as $key => $field) {
            $ret[$key] = $field->name ? : $key;
        }
        
        return $ret;
    }
    
    /**
     * @return string
     */
    public function getCasPropertyName()
    {
        return $this->casPropertyName;
    }
    
    /**
     * @return string
     */
    public function getCasField()
    {
        if ($this->casPropertyName) {
            $fieldDef = $this->fieldDefinitions[$this->casPropertyName];
            if (!$fieldDef) {
                throw new ODMException("CAS property " . $this->casPropertyName . " doesn't have a field definition");
            }
            
            return $fieldDef->name ? : $this->casPropertyName;
        }
        else {
            return '';
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
                //if (!isset($this->propertyMapping[$key])) {
                //    throw new AnnotationParsingException("Primary field " . $key . " is not defined.");
                //}
                //$propertyName       = $this->propertyMapping[$key];
                $relfectionProperty = $this->reflectionProperties[$key];
                $oldAccessibility   = $relfectionProperty->isPublic();
                $relfectionProperty->setAccessible(true);
                $value = $relfectionProperty->getValue($obj);
                $relfectionProperty->setAccessible($oldAccessibility);
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
