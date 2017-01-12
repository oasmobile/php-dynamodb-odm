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
use Symfony\Component\Finder\Finder;

class ItemManager
{
    /**
     * @var string[]
     */
    protected $possibleItemClasses = [];
    
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
    
    /**
     * @var array
     */
    protected $reservedAttributeNames = [];
    
    public function __construct(array $dynamodbConfig, $defaultTablePrefix, $cacheDir, $isDev = true)
    {
        $this->dynamodbConfig     = $dynamodbConfig;
        $this->defaultTablePrefix = $defaultTablePrefix;
        
        AnnotationRegistry::registerLoader([$this, 'loadAnnotationClass']);
        
        $this->reader = new CachedReader(
            new AnnotationReader(),
            new FilesystemCache($cacheDir),
            $isDev
        );
    }
    
    public function addNamespace($namespace, $srcDir)
    {
        $finder = new Finder();
        $finder->in($srcDir)
               ->path('/\.php$/');
        foreach ($finder as $splFileInfo) {
            $classname = sprintf(
                "%s\\%s\\%s",
                $namespace,
                str_replace("/", "\\", $splFileInfo->getRelativePath()),
                $splFileInfo->getBasename(".php")
            );
            $classname = preg_replace('#\\\\+#', '\\', $classname);
            //mdebug("Class name is %s", $classname);
            $this->possibleItemClasses[] = $classname;
        }
    }
    
    public function addReservedAttributeNames(...$args)
    {
        foreach ($args as $arg) {
            $this->reservedAttributeNames[] = $arg;
        }
    }
    
    public function clear()
    {
        $this->repositories = [];
    }
    
    public function detach($item)
    {
        if (!is_object($item)) {
            throw new ODMException("You can only detach a managed object!");
        }
        $this->getRepository(get_class($item))->detach($item);
    }
    
    public function flush()
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }
    
    public function get($itemClass, array $keys, $consistentRead = false)
    {
        $item = $this->getRepository($itemClass)->get($keys, $consistentRead);
        
        return $item;
    }
    
    /**
     * @param $className
     *
     * @internal
     * @return bool
     */
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
    
    public function refresh($item)
    {
        if (!is_object($item)) {
            throw new ODMException("You can only refresh a managed object!");
        }
        $this->getRepository(get_class($item))->refresh($item);
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
     * @param $itemClass
     *
     * @return ItemReflection
     */
    public function getItemReflection($itemClass)
    {
        if (!isset($this->itemReflections[$itemClass])) {
            $reflection = new ItemReflection($itemClass, $this->reservedAttributeNames);
            $reflection->parse($this->reader);
            $this->itemReflections[$itemClass] = $reflection;
        }
        else {
            $reflection = $this->itemReflections[$itemClass];
        }
        
        return $reflection;
    }
    
    /**
     * @return \string[]
     */
    public function getPossibleItemClasses()
    {
        return $this->possibleItemClasses;
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
            $reflection                     = $this->getItemReflection($itemClass);
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
    
    /**
     * @return array
     */
    public function getReservedAttributeNames()
    {
        return $this->reservedAttributeNames;
    }
    
    /**
     * @param array $reservedAttributeNames
     */
    public function setReservedAttributeNames($reservedAttributeNames)
    {
        $this->reservedAttributeNames = $reservedAttributeNames;
    }
    
}
