<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Field;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use Oasis\Mlib\ODM\Dynamodb\Attributes\PartitionedHashKey;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Oasis\Mlib\ODM\Dynamodb\Ut\BasicGameInfo;
use Oasis\Mlib\ODM\Dynamodb\Ut\ConsoleGame;
use Oasis\Mlib\ODM\Dynamodb\Ut\Game;
use Oasis\Mlib\ODM\Dynamodb\Ut\User;
use PHPUnit\Framework\TestCase;

class ItemReflectionTest extends TestCase
{
    // --- Attribute parsing ---

    public function testParseUserItemDefinition()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $itemDef = $ref->getItemDefinition();
        $this->assertInstanceOf(Item::class, $itemDef);
        $this->assertSame('users', $itemDef->table);
        $this->assertInstanceOf(Index::class, $itemDef->primaryIndex);
        $this->assertSame('id', $itemDef->primaryIndex->hash);
    }

    public function testParseUserGlobalSecondaryIndices()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $gsi = $ref->getItemDefinition()->globalSecondaryIndices;
        $this->assertCount(3, $gsi);
        foreach ($gsi as $index) {
            $this->assertInstanceOf(Index::class, $index);
        }
    }

    public function testParseUserFieldDefinitions()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $types = $ref->getAttributeTypes();
        $this->assertSame('number', $types['uid']);
        $this->assertSame('string', $types['name']);
        $this->assertSame('string', $types['alias']);
        $this->assertSame('number', $types['age']);
        $this->assertSame('number', $types['salary']);
        $this->assertSame('string', $types['hometown']);
        $this->assertSame('number', $types['ts']);
    }

    public function testParseUserCasProperties()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $cas = $ref->getCasProperties();
        $this->assertArrayHasKey('name', $cas);
        $this->assertSame(Field::CAS_ENABLED, $cas['name']);
        $this->assertArrayHasKey('lastUpdated', $cas);
        $this->assertSame(Field::CAS_TIMESTAMP, $cas['lastUpdated']);
    }

    public function testParseUserPartitionedHashKeys()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $phks = $ref->getPartitionedHashKeys();
        $this->assertArrayHasKey('hometownPartition', $phks);
        $phk = $phks['hometownPartition'];
        $this->assertInstanceOf(PartitionedHashKey::class, $phk);
        $this->assertSame('id', $phk->hashField);
        $this->assertSame('hometown', $phk->baseField);
        $this->assertSame(16, $phk->size);
    }

    public function testParseGameModel()
    {
        $ref = new ItemReflection(Game::class, []);
        $ref->parse();

        $itemDef = $ref->getItemDefinition();
        $this->assertSame('games', $itemDef->table);
        $this->assertSame('gamecode', $itemDef->primaryIndex->hash);
        $this->assertCount(2, $itemDef->globalSecondaryIndices);

        $phks = $ref->getPartitionedHashKeys();
        $this->assertArrayHasKey('languagePartition', $phks);
        $this->assertSame(4, $phks['languagePartition']->size);
    }

    // --- Hydrate / Dehydrate ---

    public function testHydrateCreatesNewObject()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $data = [
            'uid'      => 42,
            'name'     => 'Alice',
            'alias'    => 'ali',
            'age'      => 30,
            'salary'   => 50000,
            'hometown' => 'LA',
            'ts'       => 1234567890,
        ];

        $user = $ref->hydrate($data);
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame(42, $user->getId());
        $this->assertSame('Alice', $user->getName());
        $this->assertSame(30, $user->getAge());
    }

    public function testHydrateExistingObject()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(1);
        $user->setName('Bob');

        $data = [
            'uid'  => 1,
            'name' => 'Charlie',
        ];

        $result = $ref->hydrate($data, $user);
        $this->assertSame($user, $result);
        $this->assertSame('Charlie', $user->getName());
    }

    public function testHydrateStringCastForNullValue()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        // DynamoDB stores "" as null; hydrate should cast null to "" for string fields
        $data = [
            'uid'  => 1,
            'name' => null,
        ];

        $user = $ref->hydrate($data);
        $this->assertSame('', $user->getName());
    }

    public function testDehydrateObject()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(42);
        $user->setName('Alice');
        $user->setAge(30);
        $user->setWage(50000);
        $user->setHometown('LA');

        $data = $ref->dehydrate($user);
        $this->assertSame(42, $data['uid']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame(30, $data['age']);
        $this->assertSame(50000, $data['salary']);
        $this->assertSame('LA', $data['hometown']);
    }

    public function testDehydrateSkipsNullGsiKey()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(1);
        // dummy field is null by default
        $data = $ref->dehydrate($user);
        // dummy is not gsiKey, so null value should be included
        $this->assertArrayHasKey('dummy', $data);
        $this->assertNull($data['dummy']);
    }

    public function testHydrateDehydrateRoundTrip()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $data = [
            'uid'      => 99,
            'name'     => 'RoundTrip',
            'alias'    => 'rt',
            'age'      => 25,
            'salary'   => 60000,
            'hometown' => 'SF',
            'ts'       => 9999999,
            'dummy'    => 'test',
        ];

        $user       = $ref->hydrate($data);
        $dehydrated = $ref->dehydrate($user);

        // hometownPartition is not in original data, so skip it
        unset($dehydrated['hometownPartition']);
        foreach ($data as $key => $value) {
            $this->assertSame($value, $dehydrated[$key], "Mismatch for key: $key");
        }
    }

    // --- Property access ---

    public function testGetPropertyValue()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(42);

        $this->assertSame(42, $ref->getPropertyValue($user, 'id'));
    }

    public function testUpdateProperty()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $ref->updateProperty($user, 'id', 99);
        $this->assertSame(99, $user->getId());
    }

    // --- Primary key extraction ---

    public function testGetPrimaryKeys()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(42);

        $keys = $ref->getPrimaryKeys($user);
        $this->assertArrayHasKey('uid', $keys);
        $this->assertSame(42, $keys['uid']);
    }

    public function testGetPrimaryKeysWithPropertyNames()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(42);

        $keys = $ref->getPrimaryKeys($user, false);
        $this->assertArrayHasKey('id', $keys);
        $this->assertSame(42, $keys['id']);
    }

    public function testGetPrimaryKeysFromArray()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $keys = $ref->getPrimaryKeys(['uid' => 42]);
        $this->assertArrayHasKey('uid', $keys);
        $this->assertSame(42, $keys['uid']);
    }

    public function testGetPrimaryIdentifier()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $user->setId(42);

        $id1 = $ref->getPrimaryIdentifier($user);
        $id2 = $ref->getPrimaryIdentifier($user);
        $this->assertSame($id1, $id2);
        $this->assertNotEmpty($id1);
    }

    public function testGetPrimaryIdentifierDifferentForDifferentKeys()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user1 = new User();
        $user1->setId(1);
        $user2 = new User();
        $user2->setId(2);

        $this->assertNotSame(
            $ref->getPrimaryIdentifier($user1),
            $ref->getPrimaryIdentifier($user2)
        );
    }

    // --- Field name mapping ---

    public function testGetFieldNameMapping()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $mapping = $ref->getFieldNameMapping();
        $this->assertSame('uid', $mapping['id']);
        $this->assertSame('name', $mapping['name']);
        $this->assertSame('salary', $mapping['wage']);
        $this->assertSame('ts', $mapping['lastUpdated']);
    }

    public function testGetFieldNameByPropertyName()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $this->assertSame('uid', $ref->getFieldNameByPropertyName('id'));
        $this->assertSame('name', $ref->getFieldNameByPropertyName('name'));
        $this->assertSame('salary', $ref->getFieldNameByPropertyName('wage'));
    }

    // --- PartitionedHashKey value generation ---

    public function testGetAllPartitionedValues()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $values = $ref->getAllPartitionedValues('hometownPartition', 'LA');
        $this->assertCount(16, $values);
        foreach ($values as $v) {
            $this->assertStringStartsWith('LA-', $v);
        }
    }

    public function testGetAllPartitionedValuesNonPartitionedField()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        // For a non-partitioned field, should return just the base value
        $values = $ref->getAllPartitionedValues('nonExistentField', 'base');
        $this->assertCount(1, $values);
        $this->assertSame('base', $values[0]);
    }

    public function testGetAllPartitionedValuesGameModel()
    {
        $ref = new ItemReflection(Game::class, []);
        $ref->parse();

        $values = $ref->getAllPartitionedValues('languagePartition', 'en');
        $this->assertCount(4, $values);
        foreach ($values as $v) {
            $this->assertStringStartsWith('en-', $v);
        }
    }

    // --- NotAnnotatedException ---

    public function testParseClassWithoutItemAnnotationThrowsException()
    {
        $ref = new ItemReflection(\stdClass::class, []);
        $this->expectException(NotAnnotatedException::class);
        $ref->parse();
    }

    // --- Projected attributes ---

    public function testGetProjectedAttributesForProjectedItem()
    {
        $ref = new ItemReflection(BasicGameInfo::class, []);
        $ref->parse();

        $projected = $ref->getProjectedAttributes();
        $this->assertNotEmpty($projected);
        $this->assertContains('gamecode', $projected);
        $this->assertContains('family', $projected);
    }

    public function testGetProjectedAttributesForNonProjectedItem()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $projected = $ref->getProjectedAttributes();
        $this->assertEmpty($projected);
    }

    // --- Other accessors ---

    public function testGetItemClass()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();
        $this->assertSame(User::class, $ref->getItemClass());
    }

    public function testGetTableName()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();
        $this->assertSame('users', $ref->getTableName());
    }

    public function testGetRepositoryClassDefault()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();
        $this->assertSame(\Oasis\Mlib\ODM\Dynamodb\ItemRepository::class, $ref->getRepositoryClass());
    }

    public function testGetReflectionClass()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();
        $this->assertInstanceOf(\ReflectionClass::class, $ref->getReflectionClass());
    }

    // --- Reserved attribute names ---

    public function testHydrateSkipsReservedAttributes()
    {
        $ref = new ItemReflection(User::class, ['uid']);
        $ref->parse();

        $data = [
            'uid'  => 42,
            'name' => 'Alice',
        ];

        $user = $ref->hydrate($data);
        // uid is reserved, so it should not be hydrated
        $this->assertSame(0, $user->getId());
        $this->assertSame('Alice', $user->getName());
    }

    // --- Exception paths (Task 1.8) ---

    public function testDehydrateNonObjectThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $this->expectException(\TypeError::class);
        $ref->dehydrate('not an object');
    }

    public function testDehydrateWrongTypeObjectThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $game = new Game();
        $this->expectException(ODMException::class);
        $ref->dehydrate($game);
    }

    public function testHydrateWrongTypeObjectThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $game = new Game();
        $this->expectException(ODMException::class);
        $ref->hydrate(['uid' => 1], $game);
    }

    public function testHydrateNonObjectSecondArgThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $this->expectException(\TypeError::class);
        $ref->hydrate(['uid' => 1], 'not an object');
    }

    public function testGetPropertyValueWrongTypeThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $game = new Game();
        $this->expectException(ODMException::class);
        $ref->getPropertyValue($game, 'id');
    }

    public function testUpdatePropertyWrongTypeThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $game = new Game();
        $this->expectException(ODMException::class);
        $ref->updateProperty($game, 'id', 1);
    }

    // --- Additional coverage tests ---

    public function testGetPropertyValueNonExistentPropertyThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $this->expectException(ODMException::class);
        $ref->getPropertyValue($user, 'nonExistentProperty');
    }

    public function testUpdatePropertyNonExistentPropertyThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $user = new User();
        $this->expectException(ODMException::class);
        $ref->updateProperty($user, 'nonExistentProperty', 'value');
    }

    public function testHydrateSkipsUnknownAttributes()
    {
        // Suppress mwarning output from hydrate
        \Oasis\Mlib\Logging\MLogging::getLogger()->pushHandler(new \Monolog\Handler\NullHandler());

        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $data = [
            'uid'             => 42,
            'name'            => 'Alice',
            'unknown_field'   => 'should be skipped',
        ];

        $user = $ref->hydrate($data);
        $this->assertSame(42, $user->getId());
        $this->assertSame('Alice', $user->getName());
    }

    public function testGetPrimaryKeysFromIncompleteArrayThrowsException()
    {
        $ref = new ItemReflection(User::class, []);
        $ref->parse();

        $this->expectException(ODMException::class);
        $ref->getPrimaryKeys(['name' => 'Alice']); // missing 'uid' key
    }
}
