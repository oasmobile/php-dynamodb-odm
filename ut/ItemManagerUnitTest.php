<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\ItemRepository;
use PHPUnit_Framework_TestCase;

class ItemManagerUnitTest extends PHPUnit_Framework_TestCase
{
    private function createCacheDir()
    {
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    // --- Connection construction ---

    public function testConstructWithConnectionInstance()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);

        $im = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $this->assertSame(['region' => 'us-east-1'], $im->getDatabaseConfig());
    }

    public function testCreateDBConnectionReturnsCloneWhenConnectionProvided()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);

        $im  = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);
        $cnn = $im->createDBConnection();

        $this->assertInstanceOf(StubConnection::class, $cnn);
        $this->assertNotSame($stubCnn, $cnn);
    }

    // --- Array construction ---

    public function testConstructWithArrayConfig()
    {
        $config = ['region' => 'us-west-2', 'version' => 'latest'];
        $im     = new ItemManager($config, 'prefix_', $this->createCacheDir(), true);

        $this->assertSame($config, $im->getDatabaseConfig());
    }

    public function testCreateDBConnectionReturnsDynamoDbConnectionWhenArrayProvided()
    {
        $config = UTConfig::$dynamodbConfig;
        $im     = new ItemManager($config, 'prefix_', $this->createCacheDir(), true);
        $cnn    = $im->createDBConnection();

        $this->assertInstanceOf(DynamoDbConnection::class, $cnn);
    }

    // --- Namespace scanning ---

    public function testAddNamespaceScansClasses()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $im->addNamespace('Oasis\\Mlib\\ODM\\Dynamodb\\Ut', __DIR__);

        $classes = $im->getPossibleItemClasses();
        $this->assertNotEmpty($classes);
        $found = false;
        foreach ($classes as $class) {
            if (strpos($class, 'User') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'User class should be found in scanned namespace');
    }

    public function testAddNamespaceWithNonExistentDir()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $im->addNamespace('Fake\\Namespace', '/nonexistent/path');
        $this->assertEmpty($im->getPossibleItemClasses());
    }

    // --- Repository caching ---

    public function testGetRepositoryReturnsSameInstance()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $repo1 = $im->getRepository(User::class);
        $repo2 = $im->getRepository(User::class);

        $this->assertSame($repo1, $repo2);
        $this->assertInstanceOf(ItemRepository::class, $repo1);
    }

    // --- Operation delegation ---

    public function testPersistDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $user = new User();
        $user->setId(1);

        $im->persist($user);
        $repo = $im->getRepository(User::class);
        $this->assertInstanceOf(ItemRepository::class, $repo);
    }

    public function testDetachDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $user = new User();
        $user->setId(1);

        $im->persist($user);
        $im->detach($user);
        // No exception means success
        $this->assertTrue(true);
    }

    public function testClearDelegatesToAllRepositories()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $user = new User();
        $user->setId(1);
        $im->persist($user);

        $im->clear();
        $this->assertTrue(true);
    }

    // --- Misc accessors ---

    public function testGetDefaultTablePrefix()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, 'my-prefix-', $this->createCacheDir(), true);

        $this->assertSame('my-prefix-', $im->getDefaultTablePrefix());
    }

    public function testSkipCheckAndSet()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        $this->assertFalse($im->shouldSkipCheckAndSet());
        $im->setSkipCheckAndSet(true);
        $this->assertTrue($im->shouldSkipCheckAndSet());
    }

    public function testReservedAttributeNames()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        $this->assertEmpty($im->getReservedAttributeNames());
        $im->addReservedAttributeNames('reserved1', 'reserved2');
        $this->assertContains('reserved1', $im->getReservedAttributeNames());
        $this->assertContains('reserved2', $im->getReservedAttributeNames());
    }

    public function testGetReader()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        $reader = $im->getReader();
        $this->assertNotNull($reader);
    }

    public function testGetItemReflection()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        $ref = $im->getItemReflection(User::class);
        $this->assertNotNull($ref);
        $this->assertSame(User::class, $ref->getItemClass());
    }

    public function testGetItemReflectionCached()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        $ref1 = $im->getItemReflection(User::class);
        $ref2 = $im->getItemReflection(User::class);
        $this->assertSame($ref1, $ref2);
    }

    public function testGetDynamodbConfigDelegatesToGetDatabaseConfig()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, '', $this->createCacheDir(), true);

        /** @noinspection PhpDeprecationInspection */
        $this->assertSame($im->getDatabaseConfig(), $im->getDynamodbConfig());
    }
}
