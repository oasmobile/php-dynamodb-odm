<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:05
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console;

use Oasis\Mlib\ODM\Dynamodb\Console\Commands\CreateSchemaCommand;
use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Symfony\Component\Console\Application;
use Symfony\Component\Finder\Finder;

class ConsoleHelper
{
    /**
     * @var ItemManager
     */
    protected $itemManager;
    /**
     * @var string[]
     */
    protected $classes = [];
    
    public function __construct(ItemManager $itemManager)
    {
        $this->itemManager = $itemManager;
    }
    
    public function addNamespace($namespace, $srcDir)
    {
        $finder = new Finder();
        $finder->in($srcDir)
               ->path('/\.php$/');
        foreach ($finder as $splFileInfo) {
            $classname = sprintf(
                "%s\\%s\\%s",
                $namespace,
                str_replace("/", "\\", $splFileInfo->getRelativePath()),
                $splFileInfo->getBasename(".php")
            );
            $classname = preg_replace('#\\\\+#', '\\', $classname);
            mdebug("Class name is %s", $classname);
            $this->classes[] = $classname;
        }
    }
    
    public function addCommands(Application $application)
    {
        $application->addCommands(
            [
                (new CreateSchemaCommand())->withItemManager($this->itemManager)->withClasses($this->classes),
                //(new DropSchemaCommand())->withItemManager($this->itemManager)->withClasses($this->classes),
                //(new UpdateSchemaCommand())->withItemManager($this->itemManager)->withClasses($this->classes),
            ]
        );
    }
}
