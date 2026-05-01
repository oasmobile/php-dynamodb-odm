<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use PHPUnit_Framework_TestCase;

class IndexTest extends PHPUnit_Framework_TestCase
{
    public function testPositionalArrayConstructionHashOnly()
    {
        $index = new Index(['id']);
        $this->assertSame('id', $index->hash);
        $this->assertSame('', $index->range);
        $this->assertSame('', $index->name);
    }

    public function testPositionalArrayConstructionHashAndRange()
    {
        $index = new Index(['hometown', 'age']);
        $this->assertSame('hometown', $index->hash);
        $this->assertSame('age', $index->range);
        $this->assertSame('', $index->name);
    }

    public function testPositionalArrayConstructionWithName()
    {
        $index = new Index(['hometown', 'age', 'hometown-age-index']);
        $this->assertSame('hometown', $index->hash);
        $this->assertSame('age', $index->range);
        $this->assertSame('hometown-age-index', $index->name);
    }

    public function testNamedArrayConstruction()
    {
        $index = new Index(['hash' => 'pk', 'range' => 'sk', 'name' => 'my-index']);
        $this->assertSame('pk', $index->hash);
        $this->assertSame('sk', $index->range);
        $this->assertSame('my-index', $index->name);
    }

    public function testNamedArrayConstructionHashOnly()
    {
        $index = new Index(['hash' => 'pk']);
        $this->assertSame('pk', $index->hash);
        $this->assertSame('', $index->range);
        $this->assertSame('', $index->name);
    }

    public function testGetKeysHashOnly()
    {
        $index = new Index(['id']);
        $keys  = $index->getKeys();
        $this->assertCount(1, $keys);
        $this->assertSame('id', $keys[0]);
    }

    public function testGetKeysHashAndRange()
    {
        $index = new Index(['hometown', 'age']);
        $keys  = $index->getKeys();
        $this->assertCount(2, $keys);
        $this->assertSame('hometown', $keys[0]);
        $this->assertSame('age', $keys[1]);
    }

    public function testEmptyHashThrowsException()
    {
        $this->expectException(AnnotationParsingException::class);
        new Index([]);
    }

    public function testGetDynamodbIndex()
    {
        $index = new Index(['hash' => 'id', 'range' => 'age', 'name' => 'id-age-index']);

        $fieldNameMapping = [
            'id'  => 'uid',
            'age' => 'age',
        ];
        $attributeTypes = [
            'uid' => 'number',
            'age' => 'number',
        ];

        $dynamodbIndex = $index->getDynamodbIndex($fieldNameMapping, $attributeTypes);
        $this->assertInstanceOf(\Oasis\Mlib\AwsWrappers\DynamoDbIndex::class, $dynamodbIndex);
    }

    public function testGetDynamodbIndexHashOnly()
    {
        $index = new Index(['hash' => 'id']);

        $fieldNameMapping = [
            'id' => 'uid',
        ];
        $attributeTypes = [
            'uid' => 'string',
        ];

        $dynamodbIndex = $index->getDynamodbIndex($fieldNameMapping, $attributeTypes);
        $this->assertInstanceOf(\Oasis\Mlib\AwsWrappers\DynamoDbIndex::class, $dynamodbIndex);
    }
}
