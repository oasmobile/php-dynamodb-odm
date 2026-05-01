<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\DBAL\Schema\AbstractSchemaTool;

/**
 * Stub SchemaTool for unit testing Console Commands without DynamoDB.
 */
class StubSchemaTool extends AbstractSchemaTool
{
    /** @var array Record of method calls for verification */
    public array $calls = [];

    public function createSchema(bool $skipExisting, bool $dryRun): void
    {
        $this->calls[] = ['createSchema', $skipExisting, $dryRun];
    }

    public function updateSchema(bool $isDryRun): void
    {
        $this->calls[] = ['updateSchema', $isDryRun];
    }

    public function dropSchema(): void
    {
        $this->calls[] = ['dropSchema'];
    }
}
