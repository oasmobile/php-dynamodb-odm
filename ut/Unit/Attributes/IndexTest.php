<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Attributes;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Index;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testConstructionHashOnly()
    {
        $index = new Index(hash: 'id');
        $this->assertSame('id', $index->hash);
        $this->assertSame('', $index->range);
        $this->assertSame('', $index->name);
    }

    public function testConstructionHashAndRange()
    {
        $index = new Index(hash: 'hometown', range: 'age');
        $this->assertSame('hometown', $index->hash);
        $this->assertSame('age', $index->range);
        $this->assertSame('', $index->name);
    }

    public function testConstructionWithName()
    {
        $index = new Index(hash: 'hometown', range: 'age', name: 'hometown-age-index');
        $this->assertSame('hometown', $index->hash);
        $this->assertSame('age', $index->range);
        $this->assertSame('hometown-age-index', $index->name);
    }

    public function testGetKeysHashOnly()
    {
        $index = new Index(hash: 'id');
        $keys  = $index->getKeys();
        $this->assertCount(1, $keys);
        $this->assertSame('id', $keys[0]);
    }

    public function testGetKeysHashAndRange()
    {
        $index = new Index(hash: 'hometown', range: 'age');
        $keys  = $index->getKeys();
        $this->assertCount(2, $keys);
        $this->assertSame('hometown', $keys[0]);
        $this->assertSame('age', $keys[1]);
    }

    public function testEmptyHashThrowsException()
    {
        $this->expectException(AnnotationParsingException::class);
        new Index();
    }

    public function testGetDynamodbIndex()
    {
        $index = new Index(hash: 'id', range: 'age', name: 'id-age-index');

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
        $index = new Index(hash: 'id');

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
