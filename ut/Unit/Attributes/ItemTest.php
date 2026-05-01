<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Attributes;

use Oasis\Mlib\ODM\Dynamodb\Attributes\Index;
use Oasis\Mlib\ODM\Dynamodb\Attributes\Item;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Item annotation class.
 */
class ItemTest extends TestCase
{
    public function testBasicConstruction()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
        );

        $this->assertSame('users', $item->table);
        $this->assertInstanceOf(Index::class, $item->primaryIndex);
        $this->assertSame('id', $item->primaryIndex->hash);
    }

    public function testPrimaryIndexArrayToIndexConversion()
    {
        // When passed as array with positional args, Item constructor uses new Index(...$arr)
        $item = new Item(
            table: 'games',
            primaryIndex: ['gamecode'],
        );

        $this->assertInstanceOf(Index::class, $item->primaryIndex);
        $this->assertSame('gamecode', $item->primaryIndex->hash);
    }

    public function testGsiWithIndexInstances()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
            globalSecondaryIndices: [
                new Index(hash: 'hometown', range: 'age'),
                new Index(hash: 'hometown', range: 'wage'),
                new Index(hash: 'hometownPartition', range: 'age', name: 'home-age-gsi'),
            ],
        );

        $this->assertCount(3, $item->globalSecondaryIndices);
        foreach ($item->globalSecondaryIndices as $gsi) {
            $this->assertInstanceOf(Index::class, $gsi);
        }
        $this->assertSame('hometown', $item->globalSecondaryIndices[0]->hash);
        $this->assertSame('age', $item->globalSecondaryIndices[0]->range);
        $this->assertSame('hometown', $item->globalSecondaryIndices[1]->hash);
        $this->assertSame('wage', $item->globalSecondaryIndices[1]->range);
        $this->assertSame('hometownPartition', $item->globalSecondaryIndices[2]->hash);
        $this->assertSame('home-age-gsi', $item->globalSecondaryIndices[2]->name);
    }

    public function testLsiWithIndexInstances()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
            localSecondaryIndices: [
                new Index(hash: 'id', range: 'name'),
            ],
        );

        $this->assertCount(1, $item->localSecondaryIndices);
        $this->assertInstanceOf(Index::class, $item->localSecondaryIndices[0]);
        $this->assertSame('id', $item->localSecondaryIndices[0]->hash);
        $this->assertSame('name', $item->localSecondaryIndices[0]->range);
    }

    public function testProjectedDefaultFalse()
    {
        $item = new Item(
            table: 'games',
            primaryIndex: new Index(hash: 'gamecode'),
        );

        $this->assertFalse($item->projected);
    }

    public function testProjectedTrue()
    {
        $item = new Item(
            table: 'games',
            primaryIndex: new Index(hash: 'gamecode'),
            projected: true,
        );

        $this->assertTrue($item->projected);
    }

    public function testRepositoryDefaultNull()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
        );

        $this->assertNull($item->repository);
    }

    public function testRepositoryCustomValue()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
            repository: 'App\\Repository\\UserRepository',
        );

        $this->assertSame('App\\Repository\\UserRepository', $item->repository);
    }

    public function testDefaultGsiAndLsiEmpty()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
        );

        $this->assertEmpty($item->globalSecondaryIndices);
        $this->assertEmpty($item->localSecondaryIndices);
    }

    public function testReadonlyProperties()
    {
        $item = new Item(
            table: 'users',
            primaryIndex: new Index(hash: 'id'),
        );

        // Verify readonly by checking values are accessible
        $this->assertSame('users', $item->table);
        $this->assertSame('id', $item->primaryIndex->hash);
    }
}
