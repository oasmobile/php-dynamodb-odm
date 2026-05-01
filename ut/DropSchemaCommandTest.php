<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\DropSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class DropSchemaCommandTest extends PHPUnit_Framework_TestCase
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
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd = new DropSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute([]);

        $this->assertTrue(true);
    }
}
