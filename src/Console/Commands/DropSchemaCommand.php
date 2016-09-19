<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:41
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\AwsWrappers\DynamoDbManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DropSchemaCommand extends AbstractSchemaCommand
{
    protected function configure()
    {
        parent::configure();
        
        $this->setName('odm:schema-tool:drop')
             ->setDescription('Drop the dynamodb tables');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $classes       = $this->getManagedItemClasses();
        $im            = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamodbConfig());
        
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            $output->writeln("Will drop table <info>$tableName</info> for class <info>$class</info> ...");
            $dynamoManager->deleteTable($tableName);
            $output->writeln('Done.');
        }
        
    }
}
