<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 19:13
 */

namespace Oasis\Mlib\Dynamodb\ODM;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use Oasis\Mlib\AwsWrappers\DynamoDbTable;
use Oasis\Mlib\Dynamodb\ODM\Annotations\Item;

class ItemManager
{
    protected $defaultTablePrefix;
    protected $defaultRegion;
    
    /** @var  AnnotationReader */
    protected $reader;
    /** @var  DynamoDbTable[] */
    protected $tables;
    /** @var  Item[] */
    protected $itemMetadatas;
    
    public function __construct($defaultRegion, $defaultTablePrefix)
    {
        $this->defaultRegion      = $defaultRegion;
        $this->defaultTablePrefix = $defaultTablePrefix;
        
        $this->reader = new CachedReader(
            new AnnotationReader(),
            new FilesystemCache(sys_get_temp_dir()),
            true
        );
    }
    
    /**
     * @param $itemClass
     *
     * @return ItemRepository
     */
    public function getRepository($itemClass)
    {
    }
    
    /**
     * @param $class
     *
     * @return DynamoDbTable
     */
    public function getTable($class)
    {
        if (isset($this->tables[$class])) {
            return $this->tables[$class];
        }
        else {
            return $this->createTable($class);
        }
    }
    
    public function persist($item)
    {
    }
    
    public function remove($item)
    {
    }
    
    public function flush()
    {
        
    }
    
    /**
     * @param $class
     *
     * @return DynamoDbTable
     */
    protected function createTable($class)
    {
        $classReflection = new \ReflectionClass($class);
        
        /** @var Item|null $item */
        $item = $this->reader->getClassAnnotation($class, Item::class);
        
    }
}
