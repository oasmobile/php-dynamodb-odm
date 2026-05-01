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
}
