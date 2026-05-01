<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 20:56
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Integration;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\ODM\Dynamodb\DBAL\Drivers\DynamoDbConnection;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\BasicGameInfo;
use Oasis\Mlib\ODM\Dynamodb\Ut\ConsoleGame;
use Oasis\Mlib\ODM\Dynamodb\Ut\Game;
use Oasis\Mlib\ODM\Dynamodb\Ut\User;
use Oasis\Mlib\ODM\Dynamodb\Ut\UTConfig;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class ItemManagerTest extends TestCase
{
    /** @var  ItemManager */
    protected $itemManager;
    /** @var  ItemManager */
    protected $itemManager2;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->itemManager  = new ItemManager(
            UTConfig::$dynamodbConfig, UTConfig::$tablePrefix
        );
        $this->itemManager2 = new ItemManager(
            new DynamoDbConnection(UTConfig::$dynamodbConfig), UTConfig::$tablePrefix
        );
    }
    
    public function testPersistAndGet()
    {
        $id   = mt_rand(1000, PHP_INT_MAX);
        $user = new User();
        $user->setId($id);
        $user->setName('Alice');
        $this->itemManager->persist($user);
        $this->itemManager->flush();
        
        /** @var User $user2 */
        $user2 = $this->itemManager->get(User::class, ['id' => $id]);
        
        $this->assertEquals($user, $user2); // user object will be reused when same primary keys are used
        $this->assertEquals('Alice', $user2->getName());
        
        return $id;
    }
    #[Depends('testPersistAndGet')]
    public function testDoublePersist($id)
    {
        $id2  = $id + 1;
        $user = new User();
        $user->setId($id2);
        $user->setName('Howard');
        $this->itemManager->persist($user);
        
        /** @var User $user2 */
        $user2 = $this->itemManager->get(User::class, ['id' => $id2]);
        
        $this->assertEquals($user, $user2); // user object will be reused when same primary keys are used
        $this->assertEquals('Howard', $user2->getName());
        
        /** @var User $user3 */
        $user3 = $this->itemManager->get(User::class, ['id' => $id2], true);
        
        $this->assertNull($user3);
    }
    #[Depends('testPersistAndGet')]
    public function testEdit($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertInstanceOf(User::class, $user);
        $this->assertNotEquals('John', $user->getName());
        $user->setName('John');
        $user->haha = 22;
        $this->itemManager->flush();
        
        $this->itemManager->clear();
        /** @var User $user2 */
        $user2 = $this->itemManager->get(User::class, ['id' => $id]);
        
        $this->assertInstanceOf(User::class, $user2);
        $this->assertTrue($user !== $user2);
        $this->assertEquals('John', $user2->getName());
        
        return $id;
    }
    #[Depends('testEdit')]
    public function testCASEnabled($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        /** @var User $user2 */
        $user2 = $this->itemManager2->get(User::class, ['id' => $id]);
        
        $user->setName('Chris');
        $this->itemManager->flush();
        
        $user2->setName('Michael');
        self::expectException(DataConsistencyException::class);
        $this->itemManager2->flush();
    }
    #[Depends('testEdit')]
    public function testCASTimestamp($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        sleep(1);
        $user->setWage(777);
        $time = time();
        $this->itemManager->flush();
        $this->itemManager->clear();
        
        $user        = $this->itemManager->get(User::class, ['id' => $id]);
        $lastUpdated = $user->getLastUpdated();
        $this->assertLessThanOrEqual(1, abs($lastUpdated - $time));
        
        return $id;
    }
    #[Depends('testCASTimestamp')]
    public function testCreatingInconsistentData($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $this->itemManager->clear();
        
        //$user->setLastUpdated(0);
        //$user->setWage(999);
        $this->itemManager->persist($user);
        
        self::expectException(DataConsistencyException::class);
        $this->itemManager->flush();
        
    }
    #[Depends('testCASTimestamp')]
    public function testUpdatingInconsistentDataWhenUsingCASTimestamp($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        /** @var User $user2 */
        $user2 = $this->itemManager2->get(User::class, ['id' => $id]);
        
        //$user->setLastUpdated(time() + 10);
        sleep(1);
        $user->setAlias('emperor');
        $this->itemManager->flush();
        $user2->setWage(999);
        
        self::expectException(DataConsistencyException::class);
        $this->itemManager2->flush();
        
    }
    #[Depends('testCASTimestamp')]
    public function testNoDoubleSetWhenFlushingTwice($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $user->setAlias('pope');
        $time = time();
        $this->itemManager->flush();
        sleep(2);
        $this->itemManager->flush();
        $lastUpdated = $user->getLastUpdated();
        $this->assertLessThanOrEqual(1, abs($lastUpdated - $time));
    }
    #[Depends('testCASTimestamp')]
    public function testNoDoubleSetWhenInsertedAreFlushedTwice()
    {
        $id   = mt_rand(1000, PHP_INT_MAX);
        $user = new User();
        $user->setId($id);
        $user->setName('Alice');
        $this->itemManager->persist($user);
        $time = time();
        $this->itemManager->flush();
        sleep(2);
        $this->itemManager->flush();
        $lastUpdated = $user->getLastUpdated();
        $this->assertLessThanOrEqual(1, abs($lastUpdated - $time));
        
        $this->itemManager->remove($user);
        $this->itemManager->flush();
        $this->itemManager->flush();
    }
    #[Depends('testCASTimestamp')]
    public function testRefresh($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id], true);
        $user->setWage(888);
        $this->itemManager->refresh($user);
        
        $this->assertEquals(777, $user->getWage());
        
        // unmanaged refresh will work when persist-if-not-managed is set to true
        $this->itemManager->clear();
        $user = new User();
        $user->setId($id);
        $user->setName('Mary');
        $user->setWage(999);
        $this->itemManager->refresh($user, true);
        
        $this->assertEquals(777, $user->getWage());
        $this->assertNotEquals('Mary', $user->getName());
        
        // refreshing detached object works too
        $this->itemManager->detach($user);
        $user = new User();
        $user->setId($id);
        $user->setName('Mary');
        $user->setWage(999);
        $this->itemManager->refresh($user, true);
        
        $this->assertEquals(777, $user->getWage());
        $this->assertNotEquals('Mary', $user->getName());
        
        $this->itemManager->flush();
        $this->itemManager->clear();
        $user = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertEquals(777, $user->getWage());
        
        $user->setWage(888);
        $this->itemManager->flush();
        $this->itemManager->clear();
        $user = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertEquals(888, $user->getWage());
        
        $user->setWage(777); // restore to 777 for other tests
        $this->itemManager->flush();
        
        return $id;
    }
    #[Depends('testRefresh')]
    public function testRefreshingJustPersistedObject($id)
    {
        $this->itemManager->clear();
        $user = new User();
        $user->setId($id);
        $user->setName('Mary');
        $user->setWage(999);
        $this->itemManager->persist($user);
        $this->expectException(ODMException::class);
        $this->itemManager->refresh($user);
    }
    #[Depends('testRefresh')]
    public function testRefreshingJustRemovedObject($id)
    {
        $this->itemManager->clear();
        $user = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->itemManager->remove($user);
        $this->expectException(ODMException::class);
        $this->itemManager->refresh($user);
    }
    #[Depends('testRefresh')]
    public function testDetach($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $user->setWage(888);
        $this->itemManager->detach($user);
        $this->itemManager->flush();
        $this->itemManager->clear();
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertEquals(777, $user->getWage());
        
        return $id;
    }
    #[Depends('testDetach')]
    public function testDelete($id)
    {
        /** @var User $user */
        $user = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertInstanceOf(User::class, $user);
        
        $this->itemManager->remove($user);
        $this->itemManager->flush();
        
        $this->itemManager->clear();
        /** @var User $user2 */
        $user2 = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertNull($user2);
        
        $this->itemManager->persist($user);
        $this->itemManager->flush();
        $user2 = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertTrue($user2 instanceof User);
        $this->itemManager->getRepository(User::class)->removeById(['id' => $id]);
        $this->itemManager->flush();
        $this->itemManager->clear();
        $user2 = $this->itemManager->get(User::class, ['id' => $id]);
        $this->assertNull($user2);
        
    }
    
    public function testQueryAndScan()
    {
        $base = mt_rand(100, PHP_INT_MAX);
        
        $users = [];
        for ($i = 0; $i < 10; ++$i) {
            $id   = $base + $i;
            $user = new User();
            $user->setId($id);
            $user->setName('Batch #' . ($i + 1));
            $user->setHometown(((($i % 2) == 0) ? 'LA' : 'NY') . $base);
            $user->setAge(46 + $i); // 46 to 55
            $user->setWage(12345);
            $users[] = $user;
            $this->itemManager->persist($user);
        }
        
        $this->itemManager->flush();
        $this->itemManager->clear();
        
        $count = $this->itemManager->getRepository(User::class)->queryCount(
            '#hometown = :hometown AND #age > :age',
            [':hometown' => 'NY' . $base, ':age' => 45],
            'hometown-age-index'
        );
        $this->assertEquals(5, $count);
        $result = $this->itemManager->getRepository(User::class)->queryAll(
            '#hometown = :hometown AND #age > :age',
            [':hometown' => 'NY' . $base, ':age' => 45],
            'hometown-age-index'
        );
        $this->assertEquals(5, count($result));
        
        $count = $this->itemManager->getRepository(User::class)->multiQueryCount(
            "hometownPartition",
            "NY" . $base,
            "#age > :age",
            [":age" => 48],
            "home-age-gsi"
        );
        $this->assertEquals(4, $count);
        
        $result = [];
        $this->itemManager->getRepository(User::class)->multiQueryAndRun(
            function ($item) use (&$result) {
                $result[] = $item;
            },
            "hometownPartition",
            "NY" . $base,
            "#age > :age",
            [":age" => 48],
            "home-age-gsi"
        );
        $this->assertEquals(4, count($result));
        
        // remove all inserted users
        $count = $this->itemManager->getRepository(User::class)->scanCount(
            '#wage = :wage AND #id BETWEEN :idmin AND :idmax ',
            [
                ':wage'  => 12345,
                ':idmin' => $base,
                ':idmax' => $base + 10,
            ]
        );
        $this->assertEquals(10, $count);
        $count = 0;
        $this->itemManager->getRepository(User::class)->scanAndRun(
            function (User $user) use (&$count) {
                $count++;
                $this->itemManager->remove($user);
            },
            '#wage = :wage AND #id BETWEEN :idmin AND :idmax ',
            [
                ':wage'  => 12345,
                ':idmin' => $base,
                ':idmax' => $base + 10,
            ],
            DynamoDbIndex::PRIMARY_INDEX,
            false,
            true,
            5
        );
        $this->assertEquals(10, $count);
        
        $this->itemManager->flush();
    }
    
    public function testBatchNewWithCASDisabled()
    {
        $base = mt_rand(100, PHP_INT_MAX);
        
        /** @var User[] $users */
        $users = [];
        $keys  = [];
        for ($i = 0; $i < 10; ++$i) {
            $id   = $base + $i;
            $user = new User();
            $user->setId($id);
            $user->setName('Batch #' . ($i + 1));
            $user->setHometown(((($i % 2) == 0) ? 'LA' : 'NY') . $base);
            $user->setAge(46 + $i); // 46 to 55
            $user->setWage(12345);
            $users[$id] = $user;
            $this->itemManager->persist($user);
            
            $keys[] = ["id" => $id];
        }
        
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
        
        return $users;
    }
    #[Depends('testBatchNewWithCASDisabled')]
    public function testBatchGet($users)
    {
        $keys[] = ["id" => -PHP_INT_MAX,]; // some non existing key
        $result = $this->itemManager->getRepository(User::class)->batchGet($keys);
        $this->assertEquals(count($keys), count($result) + 1); // we get all result except the non-existing one
        /** @var User $user */
        foreach ($result as $user) {
            $this->assertArrayHasKey($user->getId(), $users);
            $this->assertEquals($users[$user->getId()]->getName(), $user->getName());
        }
    }
    #[Depends('testBatchGet')]
    public function testRemoveAll()
    {
        $this->itemManager->getRepository(User::class)->removeAll();
        $remaining = $this->itemManager->getRepository(User::class)->scanAll(
            '',
            [],
            DynamoDbIndex::PRIMARY_INDEX,
            true
        );
        $this->assertTrue($remaining->isEmpty(), json_encode($remaining));
    }
    
    /**
     * Self-contained CRUD lifecycle test:
     * create (persist + flush) → read (get) → update (modify + flush) → delete (remove + flush)
     */
    public function testCrudLifecycle()
    {
        $id   = mt_rand(1000, PHP_INT_MAX);
        $user = new User();
        $user->setId($id);
        $user->setName('CrudTest');
        $user->setHometown('Seattle');
        $user->setAge(30);
        $user->setWage(50000);

        // CREATE: persist + flush
        $this->itemManager->persist($user);
        $this->itemManager->flush();
        $this->itemManager->clear();

        // READ: get with consistent read
        /** @var User $fetched */
        $fetched = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertInstanceOf(User::class, $fetched);
        $this->assertEquals('CrudTest', $fetched->getName());
        $this->assertEquals('Seattle', $fetched->getHometown());
        $this->assertEquals(30, $fetched->getAge());
        $this->assertEquals(50000, $fetched->getWage());

        // UPDATE: modify + flush
        $fetched->setName('CrudTestUpdated');
        $fetched->setWage(60000);
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Verify update
        /** @var User $updated */
        $updated = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertInstanceOf(User::class, $updated);
        $this->assertEquals('CrudTestUpdated', $updated->getName());
        $this->assertEquals(60000, $updated->getWage());

        // DELETE: remove + flush
        $this->itemManager->remove($updated);
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Verify deletion
        $deleted = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertNull($deleted);
    }

    /**
     * Test Query with GSI and filter expressions.
     */
    public function testQueryWithGsiAndFilterExpression()
    {
        $base = mt_rand(100, PHP_INT_MAX);
        $town = 'FilterTown' . $base;

        // Insert 10 users: ages 20-29, alternating wages 100/200
        for ($i = 0; $i < 10; ++$i) {
            $user = new User();
            $user->setId($base + $i);
            $user->setName('FilterUser' . $i);
            $user->setHometown($town);
            $user->setAge(20 + $i);
            $user->setWage(($i % 2 === 0) ? 100 : 200);
            $this->itemManager->persist($user);
        }
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Wait for GSI eventual consistency
        sleep(2);

        // Query GSI (hometown-age) with filter on wage
        $repo = $this->itemManager->getRepository(User::class);

        // Query: hometown = $town AND age >= 20, filter: wage = 200
        $result = $repo->queryAll(
            '#hometown = :hometown AND #age >= :age',
            [':hometown' => $town, ':age' => 20, ':wage' => 200],
            'hometown-age-index',
            '#wage = :wage',
        );
        // Filter for wage=200: indices 1,3,5,7,9 → 5 users
        $this->assertEquals(5, count($result));

        // Query count with filter: ages 25-29 with wage=200 → indices 5,7,9 → 3 users
        $count = $repo->queryCount(
            '#hometown = :hometown AND #age >= :age',
            [':hometown' => $town, ':age' => 25, ':wage' => 200],
            'hometown-age-index',
            '#wage = :wage',
        );
        $this->assertEquals(3, $count);

        // Cleanup
        for ($i = 0; $i < 10; ++$i) {
            $repo->removeById(['id' => $base + $i]);
        }
        $this->itemManager->flush();
    }

    /**
     * Test Scan operations including parallel scan.
     */
    public function testScanAndParallelScan()
    {
        $base  = mt_rand(100, PHP_INT_MAX);
        $marker = 'ScanMarker' . $base;

        // Insert 10 users with a unique marker in alias
        for ($i = 0; $i < 10; ++$i) {
            $user = new User();
            $user->setId($base + $i);
            $user->setName('ScanUser' . $i);
            $user->setAlias($marker);
            $user->setHometown('ScanTown' . $base);
            $user->setAge(30 + $i);
            $user->setWage(99999);
            $this->itemManager->persist($user);
        }
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
        $this->itemManager->clear();

        // Wait for GSI eventual consistency
        sleep(2);

        $repo = $this->itemManager->getRepository(User::class);

        // Query via GSI to verify data is there (avoids the PRIMARY_INDEX scan bug)
        $count = $repo->queryCount(
            '#hometown = :hometown AND #age >= :age',
            [':hometown' => 'ScanTown' . $base, ':age' => 30],
            'hometown-age-index',
        );
        $this->assertEquals(10, $count);

        // Parallel scan via GSI (parallel > 1 triggers parallelScanAndRun)
        $parallelResults = [];
        $repo->parallelScanAndRun(
            3,
            function (User $user) use (&$parallelResults, $marker) {
                if ($user->getAlias() === $marker) {
                    $parallelResults[] = $user;
                }
            },
            '#hometown = :hometown',
            [':hometown' => 'ScanTown' . $base],
            'hometown-age-index',
        );
        $this->assertEquals(10, count($parallelResults));

        // Cleanup: remove items individually
        $this->itemManager->clear();
        for ($i = 0; $i < 10; ++$i) {
            $u = $this->itemManager->get(User::class, ['id' => $base + $i], true);
            if ($u) {
                $this->itemManager->remove($u);
            }
        }
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
    }

    /**
     * Test BatchGet with multiple keys including non-existing ones.
     */
    public function testBatchGetMultipleKeys()
    {
        $base = mt_rand(100, PHP_INT_MAX);

        // Insert 5 users
        $keys = [];
        for ($i = 0; $i < 5; ++$i) {
            $user = new User();
            $user->setId($base + $i);
            $user->setName('BatchUser' . $i);
            $user->setHometown('BatchTown');
            $user->setAge(40 + $i);
            $user->setWage(10000 + $i);
            $this->itemManager->persist($user);
            $keys[] = ['id' => $base + $i];
        }
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
        $this->itemManager->clear();

        // Add non-existing keys
        $keys[] = ['id' => -1];
        $keys[] = ['id' => -2];

        $repo   = $this->itemManager->getRepository(User::class);
        $result = $repo->batchGet($keys);

        // Should get 5 results (non-existing keys are silently skipped)
        $this->assertCount(5, $result);

        // Verify all returned items are User instances
        foreach ($result as $item) {
            $this->assertInstanceOf(User::class, $item);
        }

        // Cleanup
        foreach ($result as $user) {
            $this->itemManager->remove($user);
        }
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
    }

    /**
     * Test CAS conflict detection: two ItemManagers modify the same item concurrently,
     * the second flush throws DataConsistencyException.
     */
    public function testCasConflictDetection()
    {
        $id   = mt_rand(1000, PHP_INT_MAX);
        $user = new User();
        $user->setId($id);
        $user->setName('CasConflictOriginal');
        $this->itemManager->persist($user);
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Both managers read the same item
        /** @var User $user1 */
        $user1 = $this->itemManager->get(User::class, ['id' => $id], true);
        /** @var User $user2 */
        $user2 = $this->itemManager2->get(User::class, ['id' => $id], true);

        // First manager modifies and flushes successfully
        $user1->setName('CasConflictManager1');
        $this->itemManager->flush();

        // Second manager modifies and flush should throw DataConsistencyException
        $user2->setName('CasConflictManager2');
        $this->expectException(DataConsistencyException::class);
        $this->itemManager2->flush();
    }

    /**
     * Test CAS timestamp auto-update: after flush, the CAS_TIMESTAMP field
     * is automatically updated to approximately the current time.
     */
    public function testCasTimestampAutoUpdate()
    {
        $id   = mt_rand(1000, PHP_INT_MAX);
        $user = new User();
        $user->setId($id);
        $user->setName('CasTimestampTest');
        $user->setWage(100);

        $this->itemManager->persist($user);
        $timeBefore = time();
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Read back and verify timestamp was set
        /** @var User $fetched */
        $fetched = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertNotNull($fetched->getLastUpdated());
        $this->assertLessThanOrEqual(2, abs($fetched->getLastUpdated() - $timeBefore));

        // Update and verify timestamp changes
        sleep(2);
        $fetched->setWage(200);
        $timeBeforeUpdate = time();
        $this->itemManager->flush();
        $this->itemManager->clear();

        /** @var User $updated */
        $updated = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->assertLessThanOrEqual(2, abs($updated->getLastUpdated() - $timeBeforeUpdate));
        // Timestamp should have advanced
        $this->assertGreaterThanOrEqual($timeBefore + 1, $updated->getLastUpdated());

        // Cleanup
        $this->itemManager->remove($updated);
        $this->itemManager->flush();
    }

    /**
     * Test PartitionedHashKey-based multi-query operations.
     * Uses Game model which has a PartitionedHashKey on languagePartition.
     */
    public function testPartitionedHashKeyMultiQuery()
    {
        $suffix = time();
        $repo = $this->itemManager->getRepository(Game::class);

        // Insert games with same language but different gamecodes
        $allCodes = [];
        for ($i = 0; $i < 5; ++$i) {
            $game = new Game();
            $code = 'phk-game-' . $suffix . '-' . $i;
            $game->setGamecode($code);
            $game->setFamily('action');
            $game->setLanguage('en');
            $this->itemManager->persist($game);
            $allCodes[] = $code;
        }
        // Insert games with different language
        for ($i = 0; $i < 3; ++$i) {
            $game = new Game();
            $code = 'phk-other-' . $suffix . '-' . $i;
            $game->setGamecode($code);
            $game->setFamily('rpg');
            $game->setLanguage('fr');
            $this->itemManager->persist($game);
            $allCodes[] = $code;
        }
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Multi-query using PartitionedHashKey (languagePartition is partitioned by gamecode, base=language)
        $count = $repo->multiQueryCount(
            'languagePartition',
            'en',
            '#lastUpdatedAt > :ts',
            [':ts' => 0],
            'hellokitty',
        );
        $this->assertEquals(5, $count);

        $results = [];
        $repo->multiQueryAndRun(
            function ($item) use (&$results) {
                $results[] = $item;
            },
            'languagePartition',
            'en',
            '#lastUpdatedAt > :ts',
            [':ts' => 0],
            'hellokitty',
        );
        $this->assertCount(5, $results);

        // Cleanup: remove each game by primary key
        $this->itemManager->clear();
        foreach ($allCodes as $code) {
            $game = $this->itemManager->get(Game::class, ['gamecode' => $code], true);
            if ($game) {
                $this->itemManager->remove($game);
            }
        }
        $this->itemManager->setSkipCheckAndSet(true);
        $this->itemManager->flush();
        $this->itemManager->setSkipCheckAndSet(false);
    }

    /**
     * Test projected Item read-only enforcement:
     * modifying a projected item and flushing throws ODMException.
     */
    public function testProjectedItemReadOnlyEnforcement()
    {
        // Create a Game for the projected BasicGameInfo to read
        $code = 'projected-test-' . time();
        $game = new Game();
        $game->setGamecode($code);
        $game->setFamily('puzzle');
        $game->setLanguage('ja');
        $this->itemManager->persist($game);
        $this->itemManager->flush();
        $this->itemManager->clear();

        // Read as projected item
        /** @var BasicGameInfo $basicInfo */
        $basicInfo = $this->itemManager->getRepository(BasicGameInfo::class)->get(['gamecode' => $code]);
        $this->assertInstanceOf(BasicGameInfo::class, $basicInfo);
        $this->assertEquals('puzzle', $basicInfo->getFamily());

        // Modify projected item and attempt flush — should throw ODMException
        $basicInfo->setFamily('modified');
        try {
            $this->itemManager->flush();
            $this->fail('Expected ODMException was not thrown');
        } catch (ODMException $e) {
            // expected
        }

        // Cleanup: remove the game via a fresh manager
        $cleanupManager = new ItemManager(UTConfig::$dynamodbConfig, UTConfig::$tablePrefix);
        $g = $cleanupManager->get(Game::class, ['gamecode' => $code], true);
        if ($g) {
            $cleanupManager->remove($g);
            $cleanupManager->setSkipCheckAndSet(true);
            $cleanupManager->flush();
        }
    }

    public function testGetWithAttributeKey()
    {
        self::expectException(ODMException::class);
        $this->itemManager->get(User::class, ['uid' => 10]);
    }
    
    public function testQueryWithAttributeKey()
    {
        self::expectException(ODMException::class);
        $this->itemManager->getRepository(User::class)
                          ->query(
                              '#hometown = :hometown AND #salary > :wage',
                              [':hometown' => 'NY', ':wage' => 100],
                              'hometown-salary-index'
                          );
    }
    
    public function testScanWithAttributeKey()
    {
        self::expectException(ODMException::class);
        $this->itemManager->getRepository(User::class)
                          ->scan(
                              '#hometown = :hometown AND #salary > :wage',
                              [':hometown' => 'NY', ':wage' => 100]
                          );
    }
    
    public function testUnmanagedRemove()
    {
        $user = new User();
        self::expectException(ODMException::class);
        $this->itemManager->remove($user);
    }
    
    public function testUnmanagedRefresh()
    {
        $user = new User();
        self::expectException(ODMException::class);
        $this->itemManager->refresh($user);
    }
    
    public function testUnmanagedDetach()
    {
        $user = new User();
        self::expectException(ODMException::class);
        $this->itemManager->detach($user);
    }
    
    public function testMapAndListData()
    {
        $game = new ConsoleGame();
        $game->setGamecode('ps4koi-' . time());
        $game->setFamily('ps4');
        $game->setLanguage('en');
        $game->setAchievements(
            [
                "all"   => 10,
                "hello" => 30,
                "deep"  => [
                    "a" => "xyz",
                    "b" => "jjk",
                ],
            ]
        );
        $game->setAuthors(
            [
                "james",
                "curry",
                "love",
            ]
        );
        $this->itemManager->persist($game);
        $this->itemManager->flush();
        
        $game->setAuthors(
            [
                "durant",
                "green",
            ]
        );
        $this->itemManager->flush();
        
        $game->setAchievements(
            [
                "all"   => 10,
                "hello" => 30,
                "deep"  => [
                    "a" => "xyz",
                    //"b" => "jjk",
                ],
            ]
        );
        $this->itemManager->flush();
    }
    
    public function testProjectedData()
    {
        $this->itemManager->getRepository(Game::class)->removeAll();
        
        $game = new Game();
        $game->setGamecode('narutofr');
        $game->setFamily('naruto');
        $game->setLanguage('fr');
        $this->itemManager->persist($game);
        $this->itemManager->flush();
        
        /** @var BasicGameInfo $basicInfo */
        $basicInfo = $this->itemManager->getRepository(BasicGameInfo::class)->get(['gamecode' => 'narutofr']);
        $this->assertTrue($basicInfo instanceof BasicGameInfo);
        
        $basicInfo->setFamily('helll');
        $this->expectException(ODMException::class);
        $this->itemManager->flush();
    }
}
