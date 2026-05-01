<?php

namespace Oasis\Mlib\ODM\Dynamodb\Ut\Unit\Console;

use Oasis\Mlib\ODM\Dynamodb\Console\ConsoleHelper;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\Ut\StubConnection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

class ConsoleHelperTest extends TestCase
{
    public function testAddCommandsRegistersThreeCommands()
    {
        $stubCnn = new StubConnection(['region' => 'us-east-1']);
        $im      = new ItemManager($stubCnn, 'test_');
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
        $im      = new ItemManager($stubCnn, 'test_');
        $helper  = new ConsoleHelper($im);

        $this->assertSame($im, $helper->getItemManager());
    }
}
