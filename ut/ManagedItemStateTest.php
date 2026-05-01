<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Oasis\Mlib\ODM\Dynamodb\ManagedItemState;
use PHPUnit_Framework_TestCase;

class ManagedItemStateTest extends PHPUnit_Framework_TestCase
{
    /** @var ItemReflection */
    private $userReflection;

    /** @var ItemReflection */
    private $casUserReflection;

    protected function setUp()
    {
        parent::setUp();
        /** @noinspection PhpDeprecationInspection */
        AnnotationRegistry::registerLoader('class_exists');
        $reader = new AnnotationReader();

        $this->userReflection = new ItemReflection(User::class, []);
        $this->userReflection->parse($reader);

        $this->casUserReflection = new ItemReflection(CasDemoUser::class, []);
        $this->casUserReflection->parse($reader);
    }

    // --- Dirty data detection ---

    public function testHasDirtyDataReturnsFalseWhenUnmodified()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');
        $user->setHometown('LA');

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $this->assertFalse($state->hasDirtyData());
    }

    public function testHasDirtyDataReturnsTrueWhenModified()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $user->setName('Bob');
        $this->assertTrue($state->hasDirtyData());
    }

    public function testHasDirtyDataReturnsFalseForNewState()
    {
        $user = new User();
        $user->setId(1);

        $state = new ManagedItemState($this->userReflection, $user);
        $state->setState(ManagedItemState::STATE_NEW);

        $user->setName('Changed');
        // NEW state always returns false for hasDirtyData
        $this->assertFalse($state->hasDirtyData());
    }

    public function testHasDirtyDataReturnsFalseForRemovedState()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);
        $state->setState(ManagedItemState::STATE_REMOVED);

        $user->setName('Changed');
        $this->assertFalse($state->hasDirtyData());
    }

    // --- CAS timestamp update ---

    public function testUpdateCASTimestamps()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $before = time();
        $state->updateCASTimestamps();
        $after = time();

        $lastUpdated = $user->getLastUpdated();
        $this->assertGreaterThanOrEqual($before, $lastUpdated);
        $this->assertLessThanOrEqual($after, $lastUpdated);
    }

    public function testUpdateCASTimestampsWithOffset()
    {
        $user = new User();
        $user->setId(1);

        $state = new ManagedItemState($this->userReflection, $user);

        $state->updateCASTimestamps(100);
        $lastUpdated = $user->getLastUpdated();
        $this->assertGreaterThanOrEqual(time() + 99, $lastUpdated);
    }

    // --- PartitionedHashKey update ---

    public function testUpdatePartitionedHashKeys()
    {
        $user = new User();
        $user->setId(42);
        $user->setHometown('LA');

        $state = new ManagedItemState($this->userReflection, $user);
        $state->updatePartitionedHashKeys();

        $partitionValue = $this->userReflection->getPropertyValue($user, 'hometownPartition');
        $this->assertNotNull($partitionValue);
        $this->assertStringStartsWith('LA-', $partitionValue);
    }

    public function testUpdatePartitionedHashKeysDeterministic()
    {
        $user1 = new User();
        $user1->setId(42);
        $user1->setHometown('LA');

        $user2 = new User();
        $user2->setId(42);
        $user2->setHometown('LA');

        $state1 = new ManagedItemState($this->userReflection, $user1);
        $state1->updatePartitionedHashKeys();

        $state2 = new ManagedItemState($this->userReflection, $user2);
        $state2->updatePartitionedHashKeys();

        $this->assertSame(
            $this->userReflection->getPropertyValue($user1, 'hometownPartition'),
            $this->userReflection->getPropertyValue($user2, 'hometownPartition')
        );
    }

    // --- Check condition data ---

    public function testGetCheckConditionData()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');
        $user->setLastUpdated(1234567890);

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $checkData = $state->getCheckConditionData();
        // CAS properties for User: name (cas=enabled) and lastUpdated (cas=timestamp)
        $this->assertArrayHasKey('name', $checkData);
        $this->assertSame('Alice', $checkData['name']);
        $this->assertArrayHasKey('ts', $checkData);
        $this->assertSame(1234567890, $checkData['ts']);
    }

    public function testGetCheckConditionDataWithNullOriginal()
    {
        $user = new User();
        $user->setId(1);

        // Empty original data
        $state     = new ManagedItemState($this->userReflection, $user, []);
        $checkData = $state->getCheckConditionData();

        $this->assertArrayHasKey('name', $checkData);
        $this->assertNull($checkData['name']);
        $this->assertArrayHasKey('ts', $checkData);
        $this->assertNull($checkData['ts']);
    }

    public function testGetCheckConditionDataForCasDemoUser()
    {
        $user     = new CasDemoUser();
        $user->id = 1;
        $user->name = 'Test';
        $user->ver  = 'v1';

        $data  = $this->casUserReflection->dehydrate($user);
        $state = new ManagedItemState($this->casUserReflection, $user, $data);

        $checkData = $state->getCheckConditionData();
        $this->assertArrayHasKey('ver', $checkData);
        $this->assertSame('v1', $checkData['ver']);
    }

    // --- State transitions ---

    public function testDefaultStateIsManaged()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user);

        $this->assertFalse($state->isNew());
        $this->assertFalse($state->isRemoved());
    }

    public function testStateNew()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user);
        $state->setState(ManagedItemState::STATE_NEW);

        $this->assertTrue($state->isNew());
        $this->assertFalse($state->isRemoved());
    }

    public function testStateRemoved()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user);
        $state->setState(ManagedItemState::STATE_REMOVED);

        $this->assertFalse($state->isNew());
        $this->assertTrue($state->isRemoved());
    }

    public function testStateTransitionNewToManagedToRemoved()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user);

        $state->setState(ManagedItemState::STATE_NEW);
        $this->assertTrue($state->isNew());

        $state->setState(ManagedItemState::STATE_MANAGED);
        $this->assertFalse($state->isNew());
        $this->assertFalse($state->isRemoved());

        $state->setState(ManagedItemState::STATE_REMOVED);
        $this->assertTrue($state->isRemoved());
    }

    // --- Item and original data accessors ---

    public function testGetItem()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user);
        $this->assertSame($user, $state->getItem());
    }

    public function testSetItem()
    {
        $user1 = new User();
        $user2 = new User();
        $state = new ManagedItemState($this->userReflection, $user1);
        $state->setItem($user2);
        $this->assertSame($user2, $state->getItem());
    }

    public function testGetOriginalData()
    {
        $data  = ['uid' => 1, 'name' => 'Alice'];
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user, $data);
        $this->assertSame($data, $state->getOriginalData());
    }

    public function testSetOriginalData()
    {
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user, []);
        $state->setOriginalData(['uid' => 2]);
        $this->assertSame(['uid' => 2], $state->getOriginalData());
    }

    public function testGetOriginalValue()
    {
        $data  = ['uid' => 1, 'name' => 'Alice'];
        $user  = new User();
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $this->assertSame(1, $state->getOriginalValue('uid'));
        $this->assertSame('Alice', $state->getOriginalValue('name'));
        $this->assertNull($state->getOriginalValue('nonexistent'));
    }

    public function testSetUpdated()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('Alice');

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        $user->setName('Bob');
        $this->assertTrue($state->hasDirtyData());

        $state->setUpdated();
        $this->assertFalse($state->hasDirtyData());
    }

    // --- Null/empty string equivalence in dirty check ---

    public function testDirtyCheckNullEmptyStringEquivalence()
    {
        $user = new User();
        $user->setId(1);
        $user->setName('');

        // Original data has null for name (DynamoDB stores "" as null)
        $data  = $this->userReflection->dehydrate($user);
        $data['name'] = null;
        $state = new ManagedItemState($this->userReflection, $user, $data);

        // name is "" in object but null in original data — should be considered equal
        $this->assertFalse($state->hasDirtyData());
    }

    // --- PartitionedHashKey with custom hash function ---

    public function testUpdatePartitionedHashKeysWithCustomHashFunction()
    {
        $user = new User();
        $user->setId(42);
        $user->setHometown('LA');

        $state = new ManagedItemState($this->userReflection, $user);
        $state->updatePartitionedHashKeys(function ($source) {
            return strtoupper($source);
        });

        $partitionValue = $this->userReflection->getPropertyValue($user, 'hometownPartition');
        $this->assertNotNull($partitionValue);
        $this->assertStringStartsWith('LA-', $partitionValue);
    }

    // --- Dirty check with double values ---

    public function testDirtyCheckWithDoubleValues()
    {
        // Use ConsoleGame which has map/list fields
        $reader = new AnnotationReader();
        $ref    = new ItemReflection(ConsoleGame::class, []);
        $ref->parse($reader);

        $game = new ConsoleGame();
        $game->setGamecode('test');
        $game->setLanguage('en');
        $game->setFamily('ps4');

        $data  = $ref->dehydrate($game);
        $state = new ManagedItemState($ref, $game, $data);

        $this->assertFalse($state->hasDirtyData());
    }

    // --- Dirty check with array data (map/list) ---

    public function testDirtyCheckWithArrayData()
    {
        $reader = new AnnotationReader();
        $ref    = new ItemReflection(ConsoleGame::class, []);
        $ref->parse($reader);

        $game = new ConsoleGame();
        $game->setGamecode('test');
        $game->setLanguage('en');
        $game->setFamily('ps4');
        $game->setAchievements(['a' => 1, 'b' => 2]);
        $game->setAuthors(['alice', 'bob']);

        $data  = $ref->dehydrate($game);
        $state = new ManagedItemState($ref, $game, $data);

        $this->assertFalse($state->hasDirtyData());

        // Modify array data
        $game->setAchievements(['a' => 1, 'b' => 3]);
        $this->assertTrue($state->hasDirtyData());
    }

    public function testDirtyCheckWithArrayCountMismatch()
    {
        $reader = new AnnotationReader();
        $ref    = new ItemReflection(ConsoleGame::class, []);
        $ref->parse($reader);

        $game = new ConsoleGame();
        $game->setGamecode('test');
        $game->setLanguage('en');
        $game->setFamily('ps4');
        $game->setAuthors(['alice', 'bob']);

        $data  = $ref->dehydrate($game);
        $state = new ManagedItemState($ref, $game, $data);

        // Change array length
        $game->setAuthors(['alice']);
        $this->assertTrue($state->hasDirtyData());
    }

    public function testDirtyCheckWithArrayKeyMismatch()
    {
        $reader = new AnnotationReader();
        $ref    = new ItemReflection(ConsoleGame::class, []);
        $ref->parse($reader);

        $game = new ConsoleGame();
        $game->setGamecode('test');
        $game->setLanguage('en');
        $game->setFamily('ps4');
        $game->setAchievements(['a' => 1, 'b' => 2]);

        $data  = $ref->dehydrate($game);
        $state = new ManagedItemState($ref, $game, $data);

        // Change array keys
        $game->setAchievements(['a' => 1, 'c' => 2]);
        $this->assertTrue($state->hasDirtyData());
    }

    public function testDirtyCheckWithTypeMismatch()
    {
        $user = new User();
        $user->setId(1);
        $user->setAge(30);

        $data  = $this->userReflection->dehydrate($user);
        $state = new ManagedItemState($this->userReflection, $user, $data);

        // Change type: number to string
        $user->setAge('thirty');
        $this->assertTrue($state->hasDirtyData());
    }
}
