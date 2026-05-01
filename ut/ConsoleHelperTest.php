<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut;

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use PHPUnit_Framework_TestCase;
use Symfony\Component\Console\Application;

class ConsoleHelperTest extends PHPUnit_Framework_TestCase
{
    private function createCacheDir()
    {
        $dir = __DIR__ . '/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public function testAddCommandsRegistersThreeCommands()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);
        $helper  = new ConsoleHelper($im);

        $app = new Application();
        $app->setAutoExit(false);

        $beforeCount = count($app->all());
        $helper->addCommands($app);
        $afterCount = count($app->all());

        $this->assertSame(3, $afterCount - $beforeCount);
        $this->assertTrue($app->has('odm:schema-tool:create'));
        $this->assertTrue($app->has('odm:schema-tool:drop'));
        $this->assertTrue($app->has('odm:schema-tool:update'));
    }

    public function testGetItemManager()
    {
        $stubCnn = new StubConnection();
        $im      = new ItemManager($stubCnn, 'test_', $this->createCacheDir(), true);
        $helper  = new ConsoleHelper($im);

        $this->assertSame($im, $helper->getItemManager());
    }
}
