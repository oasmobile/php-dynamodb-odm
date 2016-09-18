<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:35
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\ODM\Dynamodb\ItemManager;
use Oasis\Mlib\ODM\Dynamodb\ItemReflection;
use Symfony\Component\Console\Command\Command;

abstract class AbstractSchemaCommand extends Command
{
    /** @var  ItemManager */
    protected $itemManager;
    /** @var  array */
    protected $classes;
    
    /**
     * @param ItemManager $itemManager
     *
     * @return AbstractSchemaCommand
     */
    public function withItemManager($itemManager)
    {
        $this->itemManager = $itemManager;
        
        return $this;
    }
    
    /**
     * @return array
     */
    public function getClasses()
    {
        return $this->classes;
    }
    
    /**
     * @return ItemManager
     */
    public function getItemManager()
    {
        return $this->itemManager;
    }
    
    /**
     * @param array $classes
     *
     * @return AbstractSchemaCommand
     */
    public function withClasses($classes)
    {
        $this->classes = $classes;
        
        return $this;
    }
    
    /**
     * @return ItemReflection[]
     */
    protected function getValidClasses()
    {
        foreach ($this->getClasses() as $class) {
            try {
                $reflection = $this->itemManager->getItemReflection($class);
            } catch (\Exception $e) {
                continue;
            }
            $classes[$class] = $reflection;
        }
        
        return $classes;
    }
}
