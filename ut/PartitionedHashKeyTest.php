<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\PartitionedHashKey;
use PHPUnit_Framework_TestCase;

class PartitionedHashKeyTest extends PHPUnit_Framework_TestCase
{
    public function testDefaultSize()
    {
        $phk = new PartitionedHashKey();
        $this->assertSame(16, $phk->size);
    }

    public function testConstructionWithAllProperties()
    {
        $phk            = new PartitionedHashKey();
        $phk->hashField = 'id';
        $phk->baseField = 'hometown';
        $phk->size      = 32;

        $this->assertSame('id', $phk->hashField);
        $this->assertSame('hometown', $phk->baseField);
        $this->assertSame(32, $phk->size);
    }

    public function testHashFieldAndBaseFieldDefaultNull()
    {
        $phk = new PartitionedHashKey();
        $this->assertNull($phk->hashField);
        $this->assertNull($phk->baseField);
    }

    public function testCustomSize()
    {
        $phk       = new PartitionedHashKey();
        $phk->size = 4;
        $this->assertSame(4, $phk->size);
    }
}
