<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 18:41
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Aws\DynamoDb\Exception\DynamoDbException;
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
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamoDbClient());
        
        $waits = [];
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            $output->writeln("Will drop table <info>$tableName</info> for class <info>$class</info> ...");
            try {
                $dynamoManager->deleteTable($tableName);
            } catch (DynamoDbException $e) {
                if ("ResourceNotFoundException" == $e->getAwsErrorCode()) {
                    $output->writeln('<error>Table not found.</error>');
                }
                else {
                    throw $e;
                }
            }
            $waits[] = $dynamoManager->waitForTableDeletion(
                $tableName,
                60,
                1,
                false
            );
            $output->writeln('Deleted.');
        }
        $output->writeln("Waiting for all talbes to be inactive");
        \GuzzleHttp\Promise\all($waits)->wait();
        $output->writeln("Done.");
    }
}
