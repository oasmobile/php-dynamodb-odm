<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 19:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\FilesystemCache;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;

class ItemManager
{
    protected $dynamodbConfig;
    protected $defaultTablePrefix;
    
    /** @var  AnnotationReader */
    protected $reader;
    /**
     * @var ItemReflection[]
     * Maps item class to item relfection
     */
    protected $itemReflections;
    /**
     * @var ItemRepository[]
     * Maps item class to corresponding repository
     */
    protected $repositories = [];
    
    public function __construct(array $dynamodbConfig, $defaultTablePrefix, $cacheDir, $isDebug = true)
    {
        $this->dynamodbConfig     = $dynamodbConfig;
        $this->defaultTablePrefix = $defaultTablePrefix;
        
        AnnotationRegistry::registerLoader([$this, 'loadAnnotationClass']);
        
        $this->reader = new CachedReader(
            new AnnotationReader(),
            new FilesystemCache($cacheDir),
            $isDebug
        );
    }
    
    public function get($itemClass, array $keys, $consistentRead = false)
    {
        $item = $this->getRepository($itemClass)->get($keys, $consistentRead);
        
        return $item;
    }
    
    public function flush()
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }
    
    public function loadAnnotationClass($className)
    {
        if (class_exists($className)) {
            return true;
        }
        else {
            return false;
        }
    }
    
    public function persist($item)
    {
        $this->getRepository(get_class($item))->persist($item);
    }
    
    public function remove($item)
    {
        if (!is_object($item)) {
            throw new ODMException("You can only removed a managed object!");
        }
        $this->getRepository(get_class($item))->remove($item);
    }
    
    /**
     * @return mixed
     */
    public function getDefaultTablePrefix()
    {
        return $this->defaultTablePrefix;
    }
    
    /**
     * @return array
     */
    public function getDynamodbConfig()
    {
        return $this->dynamodbConfig;
    }
    
    /**
     * @return AnnotationReader
     */
    public function getReader()
    {
        return $this->reader;
    }
    
    /**
     * @param $itemClass
     *
     * @return ItemRepository
     */
    public function getRepository($itemClass)
    {
        if (!isset($this->repositories[$itemClass])) {
            if (!isset($this->itemReflections[$itemClass])) {
                $reflection = new ItemReflection($itemClass);
                $reflection->parse($this->reader);
                $this->itemReflections[$itemClass] = $reflection;
            }
            else {
                $reflection = $this->itemReflections[$itemClass];
            }
            
            $repoClass                      = $reflection->getRepositoryClass();
            $repo                           = new $repoClass(
                $reflection,
                $this
            );
            $this->repositories[$itemClass] = $repo;
        }
        else {
            $repo = $this->repositories[$itemClass];
        }
        
        return $repo;
    }
    
}
