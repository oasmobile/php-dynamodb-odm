<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Annotations\Item;
use PHPUnit_Framework_TestCase;

/**
 * Tests for the Item annotation class.
 */
class ItemTest extends PHPUnit_Framework_TestCase
{
    public function testBasicConstruction()
    {
        $item = new Item([
            'table'        => 'users',
            'primaryIndex' => new Index(['id']),
        ]);

        $this->assertSame('users', $item->table);
        $this->assertInstanceOf(Index::class, $item->primaryIndex);
        $this->assertSame('id', $item->primaryIndex->hash);
    }

    public function testPrimaryIndexArrayToIndexConversion()
    {
        $item = new Item([
            'table'        => 'games',
            'primaryIndex' => ['gamecode'],
        ]);

        $this->assertInstanceOf(Index::class, $item->primaryIndex);
        $this->assertSame('gamecode', $item->primaryIndex->hash);
    }

    public function testGsiArrayToIndexBatchConversion()
    {
        $item = new Item([
            'table'                  => 'users',
            'primaryIndex'           => ['id'],
            'globalSecondaryIndices' => [
                ['hometown', 'age'],
                ['hometown', 'wage'],
                new Index(['hash' => 'hometownPartition', 'range' => 'age', 'name' => 'home-age-gsi']),
            ],
        ]);

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

    public function testLsiArrayToIndexBatchConversion()
    {
        $item = new Item([
            'table'                 => 'users',
            'primaryIndex'          => ['id'],
            'localSecondaryIndices' => [
                ['id', 'name'],
            ],
        ]);

        $this->assertCount(1, $item->localSecondaryIndices);
        $this->assertInstanceOf(Index::class, $item->localSecondaryIndices[0]);
        $this->assertSame('id', $item->localSecondaryIndices[0]->hash);
        $this->assertSame('name', $item->localSecondaryIndices[0]->range);
    }

    public function testProjectedDefaultFalse()
    {
        $item = new Item([
            'table'        => 'games',
            'primaryIndex' => ['gamecode'],
        ]);

        $this->assertFalse($item->projected);
    }

    public function testProjectedTrue()
    {
        $item = new Item([
            'table'        => 'games',
            'primaryIndex' => ['gamecode'],
            'projected'    => true,
        ]);

        $this->assertTrue($item->projected);
    }

    public function testRepositoryDefaultNull()
    {
        $item = new Item([
            'table'        => 'users',
            'primaryIndex' => ['id'],
        ]);

        $this->assertNull($item->repository);
    }

    public function testRepositoryCustomValue()
    {
        $item = new Item([
            'table'        => 'users',
            'primaryIndex' => ['id'],
            'repository'   => 'App\\Repository\\UserRepository',
        ]);

        $this->assertSame('App\\Repository\\UserRepository', $item->repository);
    }

    public function testDefaultGsiAndLsiEmpty()
    {
        $item = new Item([
            'table'        => 'users',
            'primaryIndex' => ['id'],
        ]);

        $this->assertEmpty($item->globalSecondaryIndices);
        $this->assertEmpty($item->localSecondaryIndices);
    }
}
