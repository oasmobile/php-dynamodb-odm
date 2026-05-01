<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Console\Commands;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\DropSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\StubConnection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DropSchemaCommandTest extends TestCase
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

    public function testCommandHasNoCustomOptions()
    {
        $cmd = new DropSchemaCommand();
        $this->assertFalse($cmd->getDefinition()->hasOption('dry-run'));
        $this->assertFalse($cmd->getDefinition()->hasOption('skip-existing-table'));
    }

    public function testExecute()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $cmd = new DropSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertTrue(true);
    }
}
