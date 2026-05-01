<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Annotations\Field;
use PHPUnit_Framework_TestCase;

class FieldTest extends PHPUnit_Framework_TestCase
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

    public function testConstructionViaDoctrineArrayFormat()
    {
        // Doctrine annotations pass values as an associative array to the constructor
        // For Field, there is no explicit constructor, so properties are set directly
        $field       = new Field();
        $field->name = 'uid';
        $field->type = 'number';
        $field->cas  = Field::CAS_ENABLED;
        $field->gsiKey = true;

        $this->assertSame('uid', $field->name);
        $this->assertSame('number', $field->type);
        $this->assertSame(Field::CAS_ENABLED, $field->cas);
        $this->assertTrue($field->gsiKey);
    }

    public function testCasTimestampValue()
    {
        $field      = new Field();
        $field->cas = Field::CAS_TIMESTAMP;
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
            $field       = new Field();
            $field->type = $type;
            $this->assertSame($type, $field->type);
        }
    }
}
