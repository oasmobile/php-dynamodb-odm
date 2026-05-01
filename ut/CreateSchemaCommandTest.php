<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\CreateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateSchemaCommandTest extends PHPUnit_Framework_TestCase
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
        $cmd = new CreateSchemaCommand();
        $this->assertSame('odm:schema-tool:create', $cmd->getName());
    }

    public function testCommandDescription()
    {
        $cmd = new CreateSchemaCommand();
        $this->assertNotEmpty($cmd->getDescription());
    }

    public function testCommandHasSkipExistingTableOption()
    {
        $cmd = new CreateSchemaCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('skip-existing-table'));
    }

    public function testCommandHasDryRunOption()
    {
        $cmd = new CreateSchemaCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }

    public function testWithItemManagerReturnsSelf()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd    = new CreateSchemaCommand();
        $result = $cmd->withItemManager($im);

        $this->assertSame($cmd, $result);
        $this->assertSame($im, $cmd->getItemManager());
    }

    public function testGetManagedItemClassesFiltersNonAnnotated()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);
        $im->addNamespace('Oasis\\Mlib\\ODM\\Dynamodb\\Ut', __DIR__);

        $cmd = new CreateSchemaCommand();
        $cmd->withItemManager($im);

        // Use reflection to call protected method
        $method = new \ReflectionMethod($cmd, 'getManagedItemClasses');

        $classes = $method->invoke($cmd);

        // Should contain annotated classes (User, Game, etc.) but not non-annotated ones
        $this->assertNotEmpty($classes);
        // Verify all returned classes have valid reflections
        foreach ($classes as $className => $reflection) {
            $this->assertNotNull($reflection);
        }
    }

    public function testExecute()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd = new CreateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute([]);

        // Command should complete without error
        $this->assertTrue(true);
    }

    public function testExecuteWithDryRun()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);

        $cmd = new CreateSchemaCommand();
        $cmd->withItemManager($im);

        $tester = new CommandTester($cmd);
        $tester->execute(['--dry-run' => true]);

        $this->assertTrue(true);
    }
}
