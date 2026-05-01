<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Attributes;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    public function testDefaultValues()
    {
        $field = new Field();
        $this->assertNull($field->name);
        $this->assertSame('string', $field->type);
        $this->assertSame(Field::CAS_DISABLED, $field->cas);
        $this->assertFalse($field->gsiKey);
    }

    public function testConstants()
    {
        $this->assertSame('disabled', Field::CAS_DISABLED);
        $this->assertSame('enabled', Field::CAS_ENABLED);
        $this->assertSame('timestamp', Field::CAS_TIMESTAMP);
    }

    public function testConstructionWithNamedParameters()
    {
        $field = new Field(name: 'uid', type: 'number', cas: Field::CAS_ENABLED, gsiKey: true);

        $this->assertSame('uid', $field->name);
        $this->assertSame('number', $field->type);
        $this->assertSame(Field::CAS_ENABLED, $field->cas);
        $this->assertTrue($field->gsiKey);
    }

    public function testCasTimestampValue()
    {
        $field = new Field(cas: Field::CAS_TIMESTAMP);
        $this->assertSame('timestamp', $field->cas);
    }

    public function testGsiKeyDefaultFalse()
    {
        $field = new Field();
        $this->assertFalse($field->gsiKey);
    }

    public function testAllFieldTypes()
    {
        $types = ['string', 'number', 'binary', 'bool', 'null', 'list', 'map'];
        foreach ($types as $type) {
            $field = new Field(type: $type);
            $this->assertSame($type, $field->type);
        }
    }
}
