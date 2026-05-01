<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Exceptions;

use Oasis\Mlib\ODM\Dynamodb\Exceptions\AnnotationParsingException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\DataConsistencyException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\NotAnnotatedException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\UnderlyingDatabaseException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionHierarchyTest extends TestCase
{
    public function testODMExceptionExtendsRuntimeException()
    {
        $e = new ODMException('test');
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testAnnotationParsingExceptionExtendsODMException()
    {
        $e = new AnnotationParsingException('test');
        $this->assertInstanceOf(ODMException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testNotAnnotatedExceptionExtendsODMException()
    {
        $e = new NotAnnotatedException('test');
        $this->assertInstanceOf(ODMException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testUnderlyingDatabaseExceptionExtendsODMException()
    {
        $e = new UnderlyingDatabaseException('test');
        $this->assertInstanceOf(ODMException::class, $e);
        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function testDataConsistencyExceptionExtendsRuntimeExceptionNotODMException()
    {
        $e = new DataConsistencyException('test');
        $this->assertInstanceOf(RuntimeException::class, $e);
        $this->assertNotInstanceOf(ODMException::class, $e);
    }
}
