<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\ItemRepository;
use Oasis\Mlib\ODM\Dynamodb\Ut\Game;
use Oasis\Mlib\ODM\Dynamodb\Ut\StubConnection;
use Oasis\Mlib\ODM\Dynamodb\Ut\User;
use Oasis\Mlib\ODM\Dynamodb\Ut\UTConfig;
use PHPUnit\Framework\TestCase;

class ItemManagerTest extends TestCase
{
    // --- Connection construction ---

    public function testConstructWithConnectionInstance()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);

        $im = new ItemManager($stubCnn, 'test_');

        $this->assertSame(['region' => 'us-east-1'], $im->getDatabaseConfig());
    }

    public function testCreateDBConnectionReturnsCloneWhenConnectionProvided()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);

        $im  = new ItemManager($stubCnn, 'test_');
        $cnn = $im->createDBConnection();

        $this->assertInstanceOf(StubConnection::class, $cnn);
        $this->assertNotSame($stubCnn, $cnn);
    }

    // --- Array construction ---

    public function testConstructWithArrayConfig()
    {
        $config = ['region' => 'us-west-2', 'version' => 'latest'];
        $im     = new ItemManager($config, 'prefix_');

        $this->assertSame($config, $im->getDatabaseConfig());
    }

    public function testCreateDBConnectionReturnsDynamoDbConnectionWhenArrayProvided()
    {
        $config = UTConfig::$dynamodbConfig;
        $im     = new ItemManager($config, 'prefix_');
        $cnn    = $im->createDBConnection();

        $this->assertInstanceOf(DynamoDbConnection::class, $cnn);
    }

    // --- Namespace scanning ---

    public function testAddNamespaceScansClasses()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $im->addNamespace('Oasis\\Mlib\\ODM\\Dynamodb\\Ut', __DIR__ . '/..');

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
        // Suppress mwarning output from ItemManager::addNamespace()
        \Oasis\Mlib\Logging\MLogging::getLogger()->pushHandler(new \Monolog\Handler\NullHandler());

        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $im->addNamespace('Fake\\Namespace', '/nonexistent/path');
        $this->assertEmpty($im->getPossibleItemClasses());
    }

    // --- Repository caching ---

    public function testGetRepositoryReturnsSameInstance()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $repo1 = $im->getRepository(User::class);
        $repo2 = $im->getRepository(User::class);

        $this->assertSame($repo1, $repo2);
        $this->assertInstanceOf(ItemRepository::class, $repo1);
    }

    // --- Operation delegation ---

    public function testPersistDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $user = new User();
        $user->setId(1);

        $im->persist($user);
        $repo = $im->getRepository(User::class);
        $this->assertInstanceOf(ItemRepository::class, $repo);
    }

    public function testDetachDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

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
        $im      = new ItemManager($stubCnn, 'test_');

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
        $im      = new ItemManager($stubCnn, 'my-prefix-');

        $this->assertSame('my-prefix-', $im->getDefaultTablePrefix());
    }

    public function testSkipCheckAndSet()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $this->assertFalse($im->shouldSkipCheckAndSet());
        $im->setSkipCheckAndSet(true);
        $this->assertTrue($im->shouldSkipCheckAndSet());
    }

    public function testReservedAttributeNames()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $this->assertEmpty($im->getReservedAttributeNames());
        $im->addReservedAttributeNames('reserved1', 'reserved2');
        $this->assertContains('reserved1', $im->getReservedAttributeNames());
        $this->assertContains('reserved2', $im->getReservedAttributeNames());
    }

    public function testGetItemReflection()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $ref = $im->getItemReflection(User::class);
        $this->assertNotNull($ref);
        $this->assertSame(User::class, $ref->getItemClass());
    }

    public function testGetItemReflectionCached()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $ref1 = $im->getItemReflection(User::class);
        $ref2 = $im->getItemReflection(User::class);
        $this->assertSame($ref1, $ref2);
    }

    public function testGetDynamodbConfigDelegatesToGetDatabaseConfig()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn);

        /** @noinspection PhpDeprecationInspection */
        $this->assertSame($im->getDatabaseConfig(), $im->getDynamodbConfig());
    }

    // --- Delegation via ItemManager public API ---

    public function testGetDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $stubCnn->setReturnValue('get', [
            'uid' => 42, 'name' => 'Alice', 'alias' => '', 'age' => 30,
            'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null,
        ]);

        $im   = new ItemManager($stubCnn, 'test_');
        $user = $im->get(User::class, ['id' => 42]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(42, $user->getId());
    }

    public function testRemoveDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $stubCnn->setReturnValue('get', [
            'uid' => 42, 'name' => 'Alice', 'alias' => '', 'age' => 30,
            'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null,
        ]);

        $im   = new ItemManager($stubCnn, 'test_');
        $user = $im->get(User::class, ['id' => 42]);

        $im->remove($user);
        $this->assertTrue(true);
    }

    public function testFlushDelegatesToAllRepositories()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $stubCnn->setReturnValue('set', true);

        $im   = new ItemManager($stubCnn, 'test_');
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $im->persist($user);
        $im->flush();
        $this->assertTrue(true);
    }

    public function testRefreshDelegatesToRepository()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $stubCnn->setReturnValue('get', [
            'uid' => 42, 'name' => 'Refreshed', 'alias' => '', 'age' => 30,
            'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null,
        ]);

        $im   = new ItemManager($stubCnn, 'test_');
        $user = $im->get(User::class, ['id' => 42]);

        $user->setName('Modified');
        $im->refresh($user);
        $this->assertSame('Refreshed', $user->getName());
    }

    public function testDetachNonObjectThrowsException()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $this->expectException(\TypeError::class);
        $im->detach('not an object');
    }

    public function testIsSkipCheckAndSetDeprecated()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        /** @noinspection PhpDeprecationInspection */
        $this->assertFalse($im->isSkipCheckAndSet());
        $im->setSkipCheckAndSet(true);
        /** @noinspection PhpDeprecationInspection */
        $this->assertTrue($im->isSkipCheckAndSet());
    }

    public function testSetReservedAttributeNames()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn);

        $im->setReservedAttributeNames(['a', 'b']);
        $this->assertSame(['a', 'b'], $im->getReservedAttributeNames());
    }
}
