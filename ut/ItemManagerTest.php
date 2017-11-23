<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-08
 * Time: 20:56
 */

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\AwsWrappers\DynamoDbIndex;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;

class ItemManagerTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ItemManager */
    protected $itemManager;
    /** @var  ItemManager */
    protected $itemManager2;
    
    protected function setUp()
    {
        parent::setUp();
        $this->itemManager  = new ItemManager(
            UTConfig::$dynamodbConfig, UTConfig::$tablePrefix, __DIR__ . "/cache", true
        );
        $this->itemManager2 = new ItemManager(
            UTConfig::$dynamodbConfig, UTConfig::$tablePrefix, __DIR__ . "/cache", true
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
    
    /**
     * @depends testPersistAndGet
     *
     * @param $id
     */
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
    
    /**
     * @depends testPersistAndGet
     *
     * @param $id
     */
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
    
    /**
     * @depends testEdit
     *
     * @param $id
     */
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
    
    /**
     * @depends testEdit
     *
     * @param $id
     */
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
    
    /**
     * @depends testCASTimestamp
     *
     * @param $id
     */
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
    
    /**
     * @depends testCASTimestamp
     *
     * @param $id
     */
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
    
    /**
     * @depends testCASTimestamp
     *
     * @param $id
     */
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
    
    /**
     * @depends testCASTimestamp
     */
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
    
    /**
     * @depends testCASTimestamp
     *
     * @param $id
     */
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
    
    /**
     * @depends testRefresh
     *
     * @param $id
     */
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
    
    /**
     * @depends testRefresh
     *
     * @param $id
     */
    public function testRefreshingJustRemovedObject($id)
    {
        $this->itemManager->clear();
        $user = $this->itemManager->get(User::class, ['id' => $id], true);
        $this->itemManager->remove($user);
        $this->expectException(ODMException::class);
        $this->itemManager->refresh($user);
    }
    
    /**
     * @depends testRefresh
     *
     * @param $id
     */
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
    
    /**
     * @depends testDetach
     *
     * @param $id
     */
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
    
    /**
     * @depends testBatchNewWithCASDisabled
     *
     * @param User[] $users
     */
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
    
    /**
     * @depends testBatchGet
     */
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
        
        $basicInfo = $this->itemManager->getRepository(BasicGameInfo::class)->get(['gamecode' => 'narutofr']);
        $this->assertTrue($basicInfo instanceof BasicGameInfo);
    }
}
