<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\ItemRepository;
use PHPUnit_Framework_TestCase;

class ItemRepositoryTest extends PHPUnit_Framework_TestCase
{
    /** @var StubConnection */
    private $stubConnection;

    /** @var ItemManager */
    private $itemManager;

    private function createCacheDir()
    {
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    protected function setUp()
    {
        parent::setUp();

        $this->stubConnection = new StubConnection(['region' => 'us-east-1']);
        $this->itemManager    = new ItemManager(
            $this->stubConnection,
            'test_',
            $this->createCacheDir(),
            true
        );
    }

    // --- Persist ---

    public function testPersistNewObject()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $repo->persist($user);
        $this->assertTrue(true);
    }

    public function testPersistDuplicateThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $repo->persist($user);

        $this->expectException(ODMException::class);
        $repo->persist($user);
    }

    public function testPersistWrongTypeThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $game = new Game();
        $game->setGamecode('test');

        $this->expectException(ODMException::class);
        $repo->persist($game);
    }

    // --- Get ---

    public function testGetReturnsHydratedObject()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(42, $user->getId());
        $this->assertSame('Alice', $user->getName());
    }

    public function testGetReturnsNullWhenNotFound()
    {
        $this->stubConnection->setReturnValue('get', null);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 999]);

        $this->assertNull($user);
    }

    public function testGetWithUndefinedFieldThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $this->expectException(ODMException::class);
        $repo->get(['nonexistent_field' => 1]);
    }

    // --- Remove ---

    public function testRemoveAfterGet()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $repo->remove($user);
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testRemoveUnmanagedObjectThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);
        $user = new User();
        $user->setId(1);

        $this->expectException(ODMException::class);
        $repo->remove($user);
    }

    // --- Flush ---

    public function testFlushNewObjectWithSkipCAS()
    {
        $this->itemManager->setSkipCheckAndSet(true);
        $repo = $this->itemManager->getRepository(User::class);

        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $repo->persist($user);
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testFlushNewObjectWithCAS()
    {
        $this->stubConnection->setReturnValue('set', true);

        $repo = $this->itemManager->getRepository(User::class);

        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $repo->persist($user);
        $repo->flush();
        $this->assertTrue(true);
    }

    // --- Detach ---

    public function testDetachPreventsFlush()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $user->setName('Modified');
        $repo->detach($user);

        // After detach, flush should not try to update the detached object
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testDetachUnmanagedObjectThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);
        $user = new User();
        $user->setId(1);

        $this->expectException(ODMException::class);
        $repo->detach($user);
    }

    // --- Refresh ---

    public function testRefreshUpdatesObjectData()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Refreshed',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $user->setName('Modified');
        $repo->refresh($user);

        $this->assertSame('Refreshed', $user->getName());
    }

    public function testRefreshUnmanagedObjectThrowsException()
    {
        $this->stubConnection->setReturnValue('get', null);

        $repo = $this->itemManager->getRepository(User::class);
        $user = new User();
        $user->setId(1);

        $this->expectException(ODMException::class);
        $repo->refresh($user);
    }

    // --- Query field resolution ---

    public function testQueryResolvesFieldNames()
    {
        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->query(
            '#hometown = :hometown AND #age > :age',
            [':hometown' => 'LA', ':age' => 20],
            'hometown-age-index'
        );

        $this->assertEmpty($result);
    }

    public function testQueryWithUnknownFieldThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $this->expectException(ODMException::class);
        $repo->query(
            '#nonexistent = :val',
            [':val' => 'test'],
            'some-index'
        );
    }

    // --- Batch operations ---

    public function testBatchGetReturnsHydratedObjects()
    {
        $this->stubConnection->setReturnValue('batchGet', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
            ['uid' => 2, 'name' => 'Bob', 'alias' => '', 'age' => 30, 'salary' => 60000, 'hometown' => 'NY', 'ts' => 0, 'dummy' => null],
        ]);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->batchGet([['id' => 1], ['id' => 2]]);

        $this->assertCount(2, $result);
        foreach ($result as $user) {
            $this->assertInstanceOf(User::class, $user);
        }
    }

    public function testBatchGetWithUnknownFieldThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $this->expectException(ODMException::class);
        $repo->batchGet([['unknown_field' => 1]]);
    }

    // --- Projected item enforcement ---

    public function testFlushProjectedNewItemThrowsException()
    {
        $repo = $this->itemManager->getRepository(BasicGameInfo::class);

        $game = new BasicGameInfo();
        $game->setGamecode('test');
        $game->setFamily('test');

        $repo->persist($game);

        $this->expectException(ODMException::class);
        $repo->flush();
    }

    // --- Clear ---

    public function testClearRemovesAllManagedItems()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $repo->clear();

        $this->expectException(ODMException::class);
        $repo->detach($user);
    }

    // --- Query with results ---

    public function testQueryReturnsHydratedObjects()
    {
        $this->stubConnection->setReturnValue('query', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
            ['uid' => 2, 'name' => 'Bob', 'alias' => '', 'age' => 30, 'salary' => 60000, 'hometown' => 'NY', 'ts' => 0, 'dummy' => null],
        ]);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->query(
            '#hometown = :hometown',
            [':hometown' => 'LA'],
            'hometown-age-index'
        );

        $this->assertCount(2, $result);
        $this->assertInstanceOf(User::class, $result[0]);
        $this->assertInstanceOf(User::class, $result[1]);
    }

    // --- QueryAll ---

    public function testQueryAllReturnsLinkedList()
    {
        $this->stubConnection->setCallbackData('queryAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
            ['uid' => 2, 'name' => 'Bob', 'alias' => '', 'age' => 30, 'salary' => 60000, 'hometown' => 'NY', 'ts' => 0, 'dummy' => null],
        ]);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->queryAll(
            '#hometown = :hometown',
            [':hometown' => 'LA'],
            'hometown-age-index'
        );

        $this->assertInstanceOf(\SplDoublyLinkedList::class, $result);
        $this->assertSame(2, $result->count());
    }

    // --- QueryAndRun ---

    public function testQueryAndRunCallsCallback()
    {
        $this->stubConnection->setCallbackData('queryAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->queryAndRun(
            function ($item) use (&$results) {
                $results[] = $item;
            },
            '#hometown = :hometown',
            [':hometown' => 'LA'],
            'hometown-age-index'
        );

        $this->assertCount(1, $results);
        $this->assertInstanceOf(User::class, $results[0]);
    }

    // --- QueryCount ---

    public function testQueryCount()
    {
        $this->stubConnection->setReturnValue('queryCount', 5);

        $repo  = $this->itemManager->getRepository(User::class);
        $count = $repo->queryCount(
            '#hometown = :hometown',
            [':hometown' => 'LA'],
            'hometown-age-index'
        );

        $this->assertSame(5, $count);
    }

    // --- Scan with results ---

    public function testScanReturnsHydratedObjects()
    {
        $this->stubConnection->setReturnValue('scan', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->scan();

        $this->assertCount(1, $result);
        $this->assertInstanceOf(User::class, $result[0]);
    }

    // --- ScanAll ---

    public function testScanAllReturnsLinkedList()
    {
        $this->stubConnection->setCallbackData('scanAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->scanAll();

        $this->assertInstanceOf(\SplDoublyLinkedList::class, $result);
        $this->assertSame(1, $result->count());
    }

    // --- ScanAndRun ---

    public function testScanAndRunCallsCallback()
    {
        $this->stubConnection->setCallbackData('scanAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->scanAndRun(function ($item) use (&$results) {
            $results[] = $item;
        });

        $this->assertCount(1, $results);
        $this->assertInstanceOf(User::class, $results[0]);
    }

    public function testScanAndRunWithParallelGreaterThanOne()
    {
        $this->stubConnection->setCallbackData('parallelScanAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->scanAndRun(
            function ($item) use (&$results) {
                $results[] = $item;
            },
            '',
            [],
            \Oasis\Mlib\AwsWrappers\DynamoDbIndex::PRIMARY_INDEX,
            false,
            true,
            5
        );

        $this->assertCount(1, $results);
    }

    public function testScanAndRunWithInvalidParallelThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $this->expectException(\InvalidArgumentException::class);
        $repo->scanAndRun(
            function () {},
            '',
            [],
            \Oasis\Mlib\AwsWrappers\DynamoDbIndex::PRIMARY_INDEX,
            false,
            true,
            0
        );
    }

    // --- ScanCount ---

    public function testScanCount()
    {
        $this->stubConnection->setReturnValue('scanCount', 10);

        $repo  = $this->itemManager->getRepository(User::class);
        $count = $repo->scanCount();

        $this->assertSame(10, $count);
    }

    // --- ParallelScanAndRun ---

    public function testParallelScanAndRunCallsCallback()
    {
        $this->stubConnection->setCallbackData('parallelScanAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->parallelScanAndRun(
            4,
            function ($item) use (&$results) {
                $results[] = $item;
            }
        );

        $this->assertCount(1, $results);
    }

    // --- MultiQueryAndRun ---

    public function testMultiQueryAndRunCallsCallback()
    {
        $this->stubConnection->setCallbackData('multiQueryAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null, 'hometownPartition' => 'LA-0'],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->multiQueryAndRun(
            function ($item) use (&$results) {
                $results[] = $item;
            },
            'hometownPartition',
            'LA',
            '#age > :age',
            [':age' => 20],
            'home-age-gsi'
        );

        $this->assertCount(1, $results);
    }

    // --- MultiQueryCount ---

    public function testMultiQueryCount()
    {
        $this->stubConnection->setCallbackData('multiQueryAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null, 'hometownPartition' => 'LA-0'],
            ['uid' => 2, 'name' => 'Bob', 'alias' => '', 'age' => 30, 'salary' => 60000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null, 'hometownPartition' => 'LA-1'],
        ]);

        $repo  = $this->itemManager->getRepository(User::class);
        $count = $repo->multiQueryCount(
            'hometownPartition',
            'LA',
            '#age > :age',
            [':age' => 20],
            'home-age-gsi'
        );

        $this->assertSame(2, $count);
    }

    // --- RemoveById ---

    public function testRemoveById()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $repo->removeById(['id' => 42]);
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testRemoveByIdNonExistent()
    {
        $this->stubConnection->setReturnValue('get', null);

        $repo = $this->itemManager->getRepository(User::class);
        $repo->removeById(['id' => 999]);
        // Should not throw — just does nothing
        $this->assertTrue(true);
    }

    // --- Flush dirty update path ---

    public function testFlushDirtyUpdateWithSkipCAS()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $this->itemManager->setSkipCheckAndSet(true);
        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $user->setName('Modified');
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testFlushDirtyUpdateWithCAS()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);
        $this->stubConnection->setReturnValue('set', true);

        $repo = $this->itemManager->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $user->setName('Modified');
        $repo->flush();
        $this->assertTrue(true);
    }

    public function testFlushProjectedUpdateThrowsException()
    {
        $this->stubConnection->setReturnValue('get', [
            'gamecode' => 'test',
            'family'   => 'original',
        ]);

        $repo = $this->itemManager->getRepository(BasicGameInfo::class);
        $game = $repo->get(['gamecode' => 'test']);

        $game->setFamily('modified');

        $this->expectException(ODMException::class);
        $repo->flush();
    }

    // --- Refresh with persistIfNotManaged ---

    public function testRefreshWithPersistIfNotManaged()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'FromDB',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo = $this->itemManager->getRepository(User::class);
        $user = new User();
        $user->setId(42);
        $user->setName('Local');

        $repo->refresh($user, true);
        $this->assertSame('FromDB', $user->getName());
    }

    // --- Remove wrong type ---

    public function testRemoveWrongTypeThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);
        $game = new Game();
        $game->setGamecode('test');

        $this->expectException(ODMException::class);
        $repo->remove($game);
    }

    // --- Detach wrong type ---

    public function testDetachWrongTypeThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);
        $game = new Game();
        $game->setGamecode('test');

        $this->expectException(ODMException::class);
        $repo->detach($game);
    }

    // --- Refresh wrong type ---

    public function testRefreshWrongTypeThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);
        $game = new Game();
        $game->setGamecode('test');

        $this->expectException(ODMException::class);
        $repo->refresh($game);
    }

    // --- GetDynamodbTable ---

    public function testGetDynamodbTable()
    {
        $repo = $this->itemManager->getRepository(User::class);
        /** @noinspection PhpDeprecationInspection */
        $table = $repo->getDynamodbTable();
        $this->assertNotNull($table);
    }

    // --- Get returns cached managed object ---

    public function testGetReturnsCachedManagedObject()
    {
        $this->stubConnection->setReturnValue('get', [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => '',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
            'dummy'    => null,
        ]);

        $repo  = $this->itemManager->getRepository(User::class);
        $user1 = $repo->get(['id' => 42]);
        $user2 = $repo->get(['id' => 42]); // non-consistent read returns cached

        $this->assertSame($user1, $user2);
    }

    // --- Scan with filter expression field resolution ---

    public function testScanWithFilterExpression()
    {
        $this->stubConnection->setReturnValue('scan', []);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->scan(
            '#hometown = :hometown',
            [':hometown' => 'LA']
        );

        $this->assertEmpty($result);
    }

    public function testScanWithUnknownFieldThrowsException()
    {
        $repo = $this->itemManager->getRepository(User::class);

        $this->expectException(ODMException::class);
        $repo->scan('#nonexistent = :val', [':val' => 'test']);
    }

    // --- RemoveAll ---

    public function testRemoveAllWithEmptyTable()
    {
        // scanAndRun returns no data, so removeAll should complete immediately
        $this->stubConnection->setCallbackData('scanAndRun', []);

        $repo = $this->itemManager->getRepository(User::class);
        $repo->removeAll();
        $this->assertTrue(true);
    }

    // --- Flush CAS conflict (set returns false) ---

    public function testFlushNewObjectCASConflictThrowsException()
    {
        $this->stubConnection->setReturnValue('set', false);

        $repo = $this->itemManager->getRepository(User::class);
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $repo->persist($user);

        $this->expectException(\Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException::class);
        $repo->flush();
    }

    public function testFlushDirtyUpdateCASConflictThrowsException()
    {
        // Must set return value BEFORE repository creation (clone happens in constructor)
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $stubCnn->setReturnValue('get', [
            'uid' => 42, 'name' => 'Alice', 'alias' => '', 'age' => 30,
            'salary' => 50000, 'hometown' => 'LA', 'ts' => 1234567890, 'dummy' => null,
        ]);
        $stubCnn->setReturnValue('set', false);

        $im   = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);
        $repo = $im->getRepository(User::class);
        $user = $repo->get(['id' => 42]);

        $user->setName('Modified');

        $this->expectException(\Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException::class);
        $repo->flush();
    }

    // --- Query with filter expression ---

    public function testQueryWithFilterExpression()
    {
        $this->stubConnection->setReturnValue('query', []);

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->query(
            '#hometown = :hometown',
            [':hometown' => 'LA'],
            'hometown-age-index',
            '#age > :age'
        );

        $this->assertEmpty($result);
    }

    // --- MultiQueryAndRun with array of hash key values ---

    public function testMultiQueryAndRunWithMultipleHashValues()
    {
        $this->stubConnection->setCallbackData('multiQueryAndRun', [
            ['uid' => 1, 'name' => 'Alice', 'alias' => '', 'age' => 25, 'salary' => 50000, 'hometown' => 'LA', 'ts' => 0, 'dummy' => null, 'hometownPartition' => 'LA-0'],
        ]);

        $repo    = $this->itemManager->getRepository(User::class);
        $results = [];
        $repo->multiQueryAndRun(
            function ($item) use (&$results) {
                $results[] = $item;
            },
            'hometownPartition',
            ['LA', 'NY'],
            '#age > :age',
            [':age' => 20],
            'home-age-gsi'
        );

        $this->assertCount(1, $results);
    }
}
