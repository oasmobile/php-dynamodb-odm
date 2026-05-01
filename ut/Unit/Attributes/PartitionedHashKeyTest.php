<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Attributes;

use Oasis\Mlib\ODM\Dynamodb\Annotations\PartitionedHashKey;
use PHPUnit\Framework\TestCase;

class PartitionedHashKeyTest extends TestCase
{
    public function testDefaultSize()
    {
        $phk = new PartitionedHashKey(hashField: 'id', baseField: 'hometown');
        $this->assertSame(16, $phk->size);
    }

    public function testConstructionWithAllProperties()
    {
        $phk = new PartitionedHashKey(hashField: 'id', baseField: 'hometown', size: 32);

        $this->assertSame('id', $phk->hashField);
        $this->assertSame('hometown', $phk->baseField);
        $this->assertSame(32, $phk->size);
    }

    public function testCustomSize()
    {
        $phk = new PartitionedHashKey(hashField: 'gamecode', baseField: 'language', size: 4);
        $this->assertSame(4, $phk->size);
    }

    public function testRequiredFields()
    {
        $phk = new PartitionedHashKey(hashField: 'myHash', baseField: 'myBase');
        $this->assertSame('myHash', $phk->hashField);
        $this->assertSame('myBase', $phk->baseField);
    }
}
