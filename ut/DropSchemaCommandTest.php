<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\DropSchemaCommand;
use PHPUnit_Framework_TestCase;

class DropSchemaCommandTest extends PHPUnit_Framework_TestCase
{
    public function testCommandName()
    {
        $cmd = new DropSchemaCommand();
        $this->assertSame('odm:schema-tool:drop', $cmd->getName());
    }

    public function testCommandDescription()
    {
        $cmd = new DropSchemaCommand();
        $this->assertNotEmpty($cmd->getDescription());
    }

    public function testCommandHasNoOptions()
    {
        $cmd     = new DropSchemaCommand();
        $options = $cmd->getDefinition()->getOptions();
        // Symfony adds default options (help, quiet, verbose, etc.), but no custom options
        // DropSchemaCommand doesn't add any custom options
        $this->assertFalse($cmd->getDefinition()->hasOption('dry-run'));
        $this->assertFalse($cmd->getDefinition()->hasOption('skip-existing-table'));
    }
}
