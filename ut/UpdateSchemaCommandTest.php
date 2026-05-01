<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\UpdateSchemaCommand;
use PHPUnit_Framework_TestCase;

class UpdateSchemaCommandTest extends PHPUnit_Framework_TestCase
{
    public function testCommandName()
    {
        $cmd = new UpdateSchemaCommand();
        $this->assertSame('odm:schema-tool:update', $cmd->getName());
    }

    public function testCommandDescription()
    {
        $cmd = new UpdateSchemaCommand();
        $this->assertNotEmpty($cmd->getDescription());
    }

    public function testCommandHasDryRunOption()
    {
        $cmd = new UpdateSchemaCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }

    public function testDryRunOptionShortcut()
    {
        $cmd    = new UpdateSchemaCommand();
        $option = $cmd->getDefinition()->getOption('dry-run');
        $this->assertSame('d', $option->getShortcut());
    }
}
