<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-03
 * Time: 19:13
 */

namespace Oasis\Mlib\ODM\Dynamodb;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\Connection;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;
use Symfony\Component\Finder\Finder;

use function is_dir;
use function mwarning;

class ItemManager
{
    /** @var string[] */
    protected array $possibleItemClasses = [];
    
    protected ?array $databaseConfig = null;
    protected string $defaultTablePrefix;
    
    /**
     * Maps item class to item reflection
     * @var ItemReflection[]
     */
    protected array $itemReflections = [];
    /**
     * Maps item class to corresponding repository
     * @var ItemRepository[]
     */
    protected array $repositories = [];
    
    /** @var string[] */
    protected array $reservedAttributeNames = [];
    
    protected bool $skipCheckAndSet = false;

    protected ?Connection $dbConnection = null;

    /**
     * ItemManager constructor.
     *
     * @param  array|Connection  $dbCnn
     * @param  string  $defaultTablePrefix
     */
    public function __construct(array|Connection $dbCnn, string $defaultTablePrefix = '')
    {
        if ($dbCnn instanceof Connection) {
            $this->dbConnection = $dbCnn;
        }
        else {
            $this->databaseConfig = $dbCnn;
        }

        $this->defaultTablePrefix = $defaultTablePrefix;
    }
    
    public function addNamespace(string $namespace, string $srcDir): void
    {
        if (!is_dir($srcDir)) {
            mwarning("Directory %s doesn't exist.", $srcDir);
            
            return;
        }
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
    
    public function addReservedAttributeNames(string ...$args): void
    {
        foreach ($args as $arg) {
            $this->reservedAttributeNames[] = $arg;
        }
    }
    
    public function clear(): void
    {
        foreach ($this->repositories as $itemRepository) {
            $itemRepository->clear();
        }
    }
    
    public function detach(object $item): void
    {
        $this->getRepository(get_class($item))->detach($item);
    }
    
    public function flush(): void
    {
        foreach ($this->repositories as $repository) {
            $repository->flush();
        }
    }
    
    public function get(string $itemClass, array $keys, bool $consistentRead = false): ?object
    {
        return $this->getRepository($itemClass)->get($keys, $consistentRead);
    }
    
    /**
     * @deprecated use shouldSkipCheckAndSet() instead
     */
    public function isSkipCheckAndSet(): bool
    {
        return $this->skipCheckAndSet;
    }
    
    public function setSkipCheckAndSet(bool $skipCheckAndSet): void
    {
        $this->skipCheckAndSet = $skipCheckAndSet;
    }
    
    public function persist(object $item): void
    {
        $this->getRepository(get_class($item))->persist($item);
    }
    
    public function refresh(object $item, bool $persistIfNotManaged = false): void
    {
        $this->getRepository(get_class($item))->refresh($item, $persistIfNotManaged);
    }
    
    public function remove(object $item): void
    {
        $this->getRepository(get_class($item))->remove($item);
    }
    
    public function shouldSkipCheckAndSet(): bool
    {
        return $this->skipCheckAndSet;
    }
    
    public function getDefaultTablePrefix(): string
    {
        return $this->defaultTablePrefix;
    }
    
    /**
     * @return array
     * todo: set deprecated and recommend getDatabaseConfig() instead
     */
    public function getDynamodbConfig(): array
    {
        return $this->getDatabaseConfig();
    }

    public function getDatabaseConfig(): array
    {
        if ($this->dbConnection instanceof Connection) {
            return $this->dbConnection->getDatabaseConfig();
        }

        return $this->databaseConfig;
    }
    
    public function getItemReflection(string $itemClass): ItemReflection
    {
        if (!isset($this->itemReflections[$itemClass])) {
            $reflection = new ItemReflection($itemClass, $this->reservedAttributeNames);
            $reflection->parse();
            $this->itemReflections[$itemClass] = $reflection;
        }
        else {
            $reflection = $this->itemReflections[$itemClass];
        }
        
        return $reflection;
    }
    
    /**
     * @return string[]
     */
    public function getPossibleItemClasses(): array
    {
        return $this->possibleItemClasses;
    }
    
    public function getRepository(string $itemClass): ItemRepository
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
     * @return string[]
     */
    public function getReservedAttributeNames(): array
    {
        return $this->reservedAttributeNames;
    }
    
    /**
     * @param string[] $reservedAttributeNames
     */
    public function setReservedAttributeNames(array $reservedAttributeNames): void
    {
        $this->reservedAttributeNames = $reservedAttributeNames;
    }

    /**
     * Get a new database connection
     */
    public function createDBConnection(): Connection
    {
        if ($this->dbConnection instanceof Connection) {
            return clone $this->dbConnection;
        }
        else {
            // default database connection
            return new DynamoDbConnection($this->getDatabaseConfig());
        }
    }

}
