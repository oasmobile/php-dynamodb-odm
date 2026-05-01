<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Console\Commands;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\UpdateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\StubConnection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateSchemaCommandTest extends TestCase
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

    public function testExecute()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $cmd = new UpdateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertTrue(true);
    }

    public function testExecuteWithDryRun()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');

        $cmd = new UpdateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute(['--dry-run' => true]);

        $this->assertTrue(true);
    }
}
