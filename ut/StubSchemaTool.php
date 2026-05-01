<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchemaTool;

/**
 * Stub SchemaTool for unit testing Console Commands without DynamoDB.
 */
class StubSchemaTool extends AbstractSchemaTool
{
    /** @var array Record of method calls for verification */
    public $calls = [];

    public function createSchema($skipExisting, $dryRun)
    {
        $this->calls[] = ['createSchema', $skipExisting, $dryRun];
    }

    public function updateSchema($isDryRun)
    {
        $this->calls[] = ['updateSchema', $isDryRun];
    }

    public function dropSchema()
    {
        $this->calls[] = ['dropSchema'];
    }
}
