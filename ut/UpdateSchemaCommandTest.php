<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\UpdateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateSchemaCommandTest extends PHPUnit_Framework_TestCase
{
    private function createCacheDir()
    {
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

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
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd = new UpdateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertTrue(true);
    }

    public function testExecuteWithDryRun()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd = new UpdateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute(['--dry-run' => true]);

        $this->assertTrue(true);
    }
}
