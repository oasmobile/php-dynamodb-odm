<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-09-18
 * Time: 17:40
 */

namespace Oasis\Mlib\ODM\Dynamodb\Console\Commands;

use Oasis\Mlib\AwsWrappers\DynamoDbManager;
use Oasis\Mlib\ODM\Dynamodb\Exceptions\ODMException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateSchemaCommand extends AbstractSchemaCommand
{
    
    protected function configure()
    {
        parent::configure();
        
        $this->setName('odm:schema-tool:create')
             ->setDescription('Processes the schema and create corresponding tables and indices.')
             ->addOption('skip-existing-table', null, InputOption::VALUE_NONE, "skip creating existing table!")
             ->addOption(
                 'dry-run',
                 null,
                 InputOption::VALUE_NONE,
                 "output possible table creations without actually creating them."
             );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $skipExisting  = $input->getOption('skip-existing-table');
        $dryRun        = $input->getOption('dry-run');
        $im            = $this->getItemManager();
        $dynamoManager = new DynamoDbManager($this->getItemManager()->getDynamoDbClient());
        
        $classes = $this->getManagedItemClasses();
        foreach ($classes as $class => $reflection) {
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            if ($dynamoManager->listTables(sprintf("/^%s\$/", preg_quote($tableName, "/")))) {
                if (!$skipExisting && !$dryRun) {
                    throw new ODMException("Table " . $tableName . " already exists!");
                }
            }
        }
        
        $waits = [];
        foreach ($classes as $class => $reflection) {
            $itemDef = $reflection->getItemDefinition();
            if ($itemDef->projected) {
                \mnotice("Class %s is projected class, will not create table.", $class);
                continue;
            }
            
            $attributeTypes   = $reflection->getAttributeTypes();
            $fieldNameMapping = $reflection->getFieldNameMapping();
            
            $lsis = [];
            foreach ($itemDef->localSecondaryIndices as $localSecondaryIndex) {
                $lsis[] = $localSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }
            $gsis = [];
            foreach ($itemDef->globalSecondaryIndices as $globalSecondaryIndex) {
                $gsis[] = $globalSecondaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes);
            }
            
            $tableName = $im->getDefaultTablePrefix() . $reflection->getTableName();
            
            $output->writeln("Will create table <info>$tableName</info> for class <info>$class</info> ...");
            if (!$dryRun) {
                $dynamoManager->createTable(
                    $tableName,
                    $itemDef->primaryIndex->getDynamodbIndex($fieldNameMapping, $attributeTypes),
                    $lsis,
                    $gsis
                );
                
                if ($gsis) {
                    // if there is gsi, we nee to wait before creating next table
                    $output->writeln("Will wait for GSI creation ...");
                    $dynamoManager->waitForTablesToBeFullyReady($tableName, 60, 2);
                }
                else {
                    $waits[] = $dynamoManager->waitForTableCreation(
                        $tableName,
                        60,
                        1,
                        false
                    );
                }
                $output->writeln('Created.');
            }
        }
        
        if (!$dryRun) {
            $output->writeln("Waiting for all tables to be active ...");
            \GuzzleHttp\Promise\all($waits)->wait();
            $output->writeln("Done.");
        }
    }
    
}
