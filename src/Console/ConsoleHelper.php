<?php
declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:05
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\CreateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\Console\Commands\DropSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\Console\Commands\UpdateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Symfony\Component\Console\Application;

class ConsoleHelper
{
    public function __construct(
        protected ItemManager $itemManager,
    ) {
    }
    
    public function addCommands(Application $application): void
    {
        $application->addCommands(
            [
                (new CreateSchemaCommand())->withItemManager($this->itemManager),
                (new DropSchemaCommand())->withItemManager($this->itemManager),
                (new UpdateSchemaCommand())->withItemManager($this->itemManager),
            ]
        );
    }
    
    public function getItemManager(): ItemManager
    {
        return $this->itemManager;
    }
}
